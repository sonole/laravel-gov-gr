<?php

namespace Sonole\GovGr\Core\Services;

use Closure;
use Sonole\GovGr\Core\Exceptions\GovApiException;
use Sonole\GovGr\Core\Support\XmlHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Throwable;

abstract class AuditedSoapService extends BaseGovService
{
    abstract protected function namespacePrefix(): string;

    abstract protected function namespaceUri(): string;

    /** Override when a service doesn't split input/output into separate {op}InputRecord/{op}OutputRecord tags, e.g. one shared "{op}Record" tag for both. */
    protected function inputRecordTag(string $operation): string
    {
        return "{$operation}InputRecord";
    }

    /** Override to null when a service returns its whole record (output plus siblings like callSequenceId/errorRecord) instead of one drilled-down output key. */
    protected function outputRecordKey(string $operation): ?string
    {
        return "{$operation}OutputRecord";
    }

    /** Override to translate a raw WS-Security faultstring into a friendlier message. */
    protected function faultMessage(string $faultString): string
    {
        return $faultString === '' ? 'SOAP fault.' : $faultString;
    }

    /**
     * $inputRecordXml as a Closure is evaluated inside logged()'s callback,
     * once transactionId() is available. Use this for services whose input
     * record embeds it (e.g. AMKA's 'aa' field).
     *
     * $transformOutput, if given, runs inside logged()'s try block, so a
     * business-level rejection it throws still gets audit context attached
     * like any other failure. Its return value replaces $output (e.g. to
     * check a business status code, or unwrap one more level).
     *
     * @param  string|Closure(): string  $inputRecordXml
     * @param  ?callable(array<string, mixed>): array<string, mixed>  $transformOutput
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    protected function call(string $operation, string|Closure $inputRecordXml, ?Request $request, ?callable $transformOutput = null): array
    {
        $this->startTransaction($request);

        $config = $this->configFor();
        $endpoint = $config['endpoint'];
        $prefix = $this->namespacePrefix();
        $inputRecordTag = $this->inputRecordTag($operation);
        $outputRecordKey = $this->outputRecordKey($operation);

        return $this->logged($endpoint, function () use ($config, $endpoint, $prefix, $operation, $inputRecordTag, $outputRecordKey, $inputRecordXml, $transformOutput) {
            $body = $inputRecordXml instanceof Closure ? $inputRecordXml() : $inputRecordXml;

            $envelope = $this->soapEnvelope(
                $prefix,
                $this->namespaceUri(),
                $config['username'],
                $config['password'],
                $this->soapRequest($prefix, "{$operation}Request", $inputRecordTag, $body),
            );

            $response = $this->http->postXml($endpoint, $envelope);
            $output = $this->unwrap($response, $outputRecordKey);

            if ($transformOutput) {
                $output = $transformOutput($output);
            }

            return [$response->status(), $output];
        });
    }

    /** @return array{endpoint: string, username: string, password: string} */
    private function configFor(): array
    {
        return $this->credentialsFor($this->environment(), $this->section());
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GovApiException
     */
    private function unwrap(Response $response, ?string $outputRecordKey): array
    {
        try {
            $data = XmlHelper::toArray($response->body());

            if ($data === null) {
                throw $this->newException(
                    "Returned a non-XML response with status {$response->status()}: ".$this->bodySnippet($response->body())
                );
            }

            $body = $data['soap_Body'] ?? [];

            foreach ($body as $key => $value) {
                if (str_ends_with($key, '_Fault')) {
                    throw $this->newAuthenticationException($this->faultMessage(data_get($value, 'faultstring', '')));
                }
            }

            if (! $response->successful()) {
                throw $this->newException("Request failed with status {$response->status()}.");
            }

            $record = $body === [] ? [] : reset($body);
            $errorCode = data_get($record, 'errorRecord.errorCode');

            if (! empty($errorCode)) {
                throw $this->newException(data_get($record, 'errorRecord.errorDescr', $errorCode));
            }

            return $outputRecordKey === null ? $record : data_get($record, $outputRecordKey, []);
        } catch (GovApiException $e) {
            throw $e->withHttpStatus($response->status());
        }
    }

    private function bodySnippet(string $body): string
    {
        $stripped = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');

        return mb_substr($stripped, 0, 200);
    }
}
