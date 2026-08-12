<?php

namespace Sonole\GovGr\TaxRegistry\Services;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovServiceException;
use Sonole\GovGr\Core\Services\BaseGovService;
use Sonole\GovGr\Core\Support\XmlHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Throwable;

/**
 * AADE's public RgWsPublic2 AFM lookup. Unlike registryService, this call
 * carries no auditRecord/UsernameToken-per-ΚΕΔ envelope. It's SOAP 1.2
 * with a bare WS-Security UsernameToken, so it builds its own envelope
 * instead of BaseGovService::soapEnvelope()/soapRequest().
 */
class PublicLookupService extends BaseGovService
{
    private const SOAP_NAMESPACE = 'http://www.w3.org/2003/05/soap-envelope';

    private const SECURITY_NAMESPACE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    private const SERVICE_NAMESPACE = 'http://rgwspublic2/RgWsPublic2Service';

    private const RECORD_NAMESPACE = 'http://rgwspublic2/RgWsPublic2';

    private const CONTENT_TYPE = 'application/soap+xml; charset=utf-8';

    protected function service(): GovService
    {
        return GovService::TaxRegistryPublic;
    }

    protected function configKey(): string
    {
        return 'tax-registry';
    }

    protected function section(): ?string
    {
        return 'public';
    }

    /**
     * Opt-in: this call's own envelope carries no auditRecord block (see the class
     * docblock), so unlike registryService there's nothing that structurally requires
     * a local audit_records row. Off by default; set TAX_REGISTRY_PUBLIC_AUDIT_ENABLED=true
     * to log these calls too.
     */
    protected function auditingEnabled(): bool
    {
        return (bool) config('govgr.tax-registry.public.audit_enabled', false);
    }

    /**
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>|null}
     *
     * @throws Throwable
     */
    public function lookup(string $afm, ?string $afmCalledBy = null, ?Request $request = null): array
    {
        if ($this->auditingEnabled()) {
            $this->startTransaction($request);
        }

        $config = $this->configFor();
        $endpoint = $config['endpoint'];

        return $this->maybeLogged($endpoint, function () use ($config, $endpoint, $afm, $afmCalledBy) {
            $envelope = $this->buildEnvelope($config['username'], $config['password'], $afm, $afmCalledBy);

            $response = $this->http->request()
                ->withHeaders(['Content-Type' => self::CONTENT_TYPE])
                ->withBody($envelope, self::CONTENT_TYPE)
                ->post($endpoint);

            return [$response->status(), $this->unwrap($response)];
        });
    }

    private function buildEnvelope(string $username, string $password, string $afm, ?string $afmCalledBy): string
    {
        $username = XmlHelper::escape($username);
        $password = XmlHelper::escape($password);
        $afm = XmlHelper::escape($afm);
        $afmCalledByXml = $afmCalledBy !== null
            ? XmlHelper::tag('ns3:afm_called_by', $afmCalledBy)
            : '<ns3:afm_called_by/>';

        $soapNs = self::SOAP_NAMESPACE;
        $securityNs = self::SECURITY_NAMESPACE;
        $serviceNs = self::SERVICE_NAMESPACE;
        $recordNs = self::RECORD_NAMESPACE;

        return <<<SOAP
            <?xml version="1.0" encoding="UTF-8"?>
            <env:Envelope
                xmlns:env="{$soapNs}"
                xmlns:ns1="{$securityNs}"
                xmlns:ns2="{$serviceNs}"
                xmlns:ns3="{$recordNs}">
                <env:Header>
                    <ns1:Security>
                        <ns1:UsernameToken>
                            <ns1:Username>{$username}</ns1:Username>
                            <ns1:Password>{$password}</ns1:Password>
                        </ns1:UsernameToken>
                    </ns1:Security>
                </env:Header>
                <env:Body>
                    <ns2:rgWsPublic2AfmMethod>
                        <ns2:INPUT_REC>
                            {$afmCalledByXml}
                            <ns3:afm_called_for>{$afm}</ns3:afm_called_for>
                        </ns2:INPUT_REC>
                    </ns2:rgWsPublic2AfmMethod>
                </env:Body>
            </env:Envelope>
            SOAP;
    }

    /** @return array{endpoint: string, username: string, password: string} */
    private function configFor(): array
    {
        return $this->credentialsFor($this->environment(), $this->section());
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GovServiceException
     */
    private function unwrap(Response $response): array
    {
        try {
            $data = XmlHelper::toArray($response->body());

            if ($data === null) {
                throw (new GovServiceException("Public AFM lookup returned a non-XML response with status {$response->status()}."))
                    ->withService($this->service());
            }

            $body = $data['env_Body'] ?? [];

            foreach ($body as $key => $value) {
                if (str_ends_with($key, '_Fault')) {
                    throw (new GovAuthenticationException(data_get($value, 'faultstring', 'Public AFM lookup SOAP fault.')))
                        ->withService($this->service());
                }
            }

            if (! $response->successful()) {
                throw (new GovServiceException("Public AFM lookup request failed with status {$response->status()}."))
                    ->withService($this->service());
            }

            $result = $this->drillToRecord($body);

            if (! is_array($result) || ! array_key_exists('basic_rec', $result)) {
                throw (new GovServiceException('Public AFM lookup returned an unrecognized response format.'))
                    ->withService($this->service());
            }

            $errorCode = data_get($result, 'error_rec.error_code');

            if (! empty($errorCode)) {
                throw (new GovServiceException(data_get($result, 'error_rec.error_descr', $errorCode)))
                    ->withService($this->service());
            }

            return $result;
        } catch (GovServiceException $e) {
            throw $e->withHttpStatus($response->status());
        }
    }

    /**
     * rgWsPublic2AfmMethod nests the result three single-child elements deep under Body.
     *
     * @param  array<string, mixed>  $body
     */
    private function drillToRecord(array $body): mixed
    {
        $level = $body;

        for ($i = 0; $i < 3 && is_array($level) && $level !== []; $i++) {
            $level = array_pop($level);
        }

        return $level;
    }
}
