<?php

namespace Sonole\GovGr\Core\Services;

use Carbon\Carbon;
use Closure;
use Sonole\GovGr\Core\Audit\AuditIdentity;
use Sonole\GovGr\Core\Contracts\IdentityResolver;
use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Exceptions\GovApiException;
use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovServiceException;
use Sonole\GovGr\Core\Http\GovHttpClient;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Sonole\GovGr\Core\Support\XmlHelper;
use Illuminate\Http\Request;
use LogicException;
use RuntimeException;
use Throwable;

abstract class BaseGovService
{
    private ?AuditIdentity $identity = null;

    private ?Carbon $transactionDate = null;

    private ?string $protocol = null;

    private ?string $transactionId = null;

    public function __construct(
        protected readonly IdentityResolver $identityResolver,
        protected readonly GovHttpClient $http,
    ) {}

    abstract protected function service(): GovService;

    /** The top-level config module key under 'govgr', e.g. 'gemi' for config('govgr.gemi.*'), or 'tax-registry' for a module whose credentials are further split by section(). */
    abstract protected function configKey(): string;

    /** Override when this module splits its pilot/production credentials into a named sub-section under configKey(), e.g. GEMI's 'validation'/'board'/'basic_info'/'balance_sheets', or tax-registry's 'registry'/'public'. Null for a module with one flat pilot/production pair. */
    protected function section(): ?string
    {
        return null;
    }

    /** The pilot/production environment this call is made against. Defaults to 'pilot' unless config("govgr.{configKey()}.env") says otherwise. Override for a module with a different environment scheme. */
    protected function environment(): string
    {
        $env = config("govgr.{$this->configKey()}.env", 'pilot');

        return is_string($env) ? $env : 'pilot';
    }

    /** Override to throw a different exception type; defaults to a generic exception tagged with this->service(). */
    protected function newException(string $message): GovApiException
    {
        return (new GovServiceException($message))->withService($this->service());
    }

    /** @see self::newException() */
    protected function newAuthenticationException(string $message): GovApiException
    {
        return (new GovAuthenticationException($message))->withService($this->service());
    }

    /**
     * The pilot/production credentials for configKey() (and $section, if
     * this module splits credentials into named sub-sections). Shared
     * lookup and validation boilerplate behind every SOAP/REST configFor().
     *
     * @return array{endpoint: string, username: string, password: string}
     *
     * @throws GovApiException
     */
    protected function credentialsFor(string $env, ?string $section): array
    {
        $path = $section === null
            ? "govgr.{$this->configKey()}.$env"
            : "govgr.{$this->configKey()}.$section.$env";

        return $this->ensureCredentialsConfigured(config($path), $this->envVarPrefix($this->configKey(), $section, $env), $env);
    }

    /** Upper-snake-cases config-key-shaped parts into an env-var prefix, e.g. envVarPrefix('bank-confirm', null, 'pilot') -> 'BANK_CONFIRM_PILOT', or envVarPrefix('gemi', 'validation', 'pilot') -> 'GEMI_VALIDATION_PILOT'. */
    private function envVarPrefix(?string ...$parts): string
    {
        $joined = implode('_', array_filter($parts, fn (?string $part) => ! empty($part)));

        return strtoupper(str_replace(['-', '.'], '_', $joined));
    }

    /**
     * @param  ?array{endpoint?: ?string, username?: ?string, password?: ?string}  $config
     * @return array{endpoint: string, username: string, password: string}
     *
     * @throws GovApiException
     */
    private function ensureCredentialsConfigured(?array $config, string $envVarPrefix, string $env): array
    {
        if (empty($config['username']) || empty($config['password']) || empty($config['endpoint'])) {
            throw $this->newAuthenticationException(
                "Credentials are not configured for the '$env' environment. Set {$envVarPrefix}_USERNAME and {$envVarPrefix}_PASSWORD."
            );
        }

        return $config;
    }

    /**
     * Call once per public method
     *
     * @throws RuntimeException
     */
    protected function startTransaction(?Request $request = null): void
    {
        $this->auditUnit();

        $identity = $this->identityResolver->resolve($request);

        if (strlen($identity->email) > 100) {
            throw new RuntimeException(
                'The identity email resolved for this request is '.strlen($identity->email)
                .' characters, but ΚΕΔ audit records require auditUserId to be 100 characters or fewer.'
            );
        }

        $this->identity = $identity;
        $this->transactionDate = Carbon::now();
    }

    /**
     * Override to make audit logging for this service opt-in/opt-out via
     * config instead of always-on. Defaults to true, matching every
     * ΚΕΔ-audited service's existing behavior. See maybeLogged().
     */
    protected function auditingEnabled(): bool
    {
        return true;
    }

    /**
     * Like logged(), but when auditingEnabled() is false it skips creating an
     * AuditRecord entirely (and the audit_records table-exists check with
     * it), running $callback() directly instead. Use this in place of
     * logged() for a service whose auditing is opt-in - e.g. a public
     * lookup with no ΚΕΔ auditRecord requirement of its own, or
     * TaxisnetOAuth's login events.
     *
     * @param  Closure(): array{0: int, 1: mixed}  $callback  returns [statusCode, result]
     *
     * @return array{result: mixed, audit_record: array<string, mixed>|null}
     *
     * @throws Throwable
     */
    protected function maybeLogged(string $endpoint, Closure $callback): array
    {
        if (! $this->auditingEnabled()) {
            [, $result] = $callback();

            return ['result' => $result, 'audit_record' => null];
        }

        return $this->logged($endpoint, $callback);
    }

    /**
     * Runs one physical API call and gives it its own audit_records row.
     *
     * @param  Closure(): array{0: int, 1: mixed}  $callback  returns [statusCode, result]
     *
     * @return array{result: mixed, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    protected function logged(string $endpoint, Closure $callback): array
    {
        if (! $this->identity) {
            $this->throwStartTransactionNotCalled('startTransaction() must be called before logged().');
        }

        $record = AuditRecord::record([
            'transaction_date' => $this->transactionDate,
            'service' => $this->service(),
            'environment' => $this->environment(),
            'endpoint' => $endpoint,
            'user_email' => $this->identity->email,
            'user_ip' => $this->identity->ip,
            'user_id' => $this->identity->userId,
        ]);

        $this->transactionId = (string) $record->id;
        $this->protocol = $record->protocol;

        try {
            [$statusCode, $result] = $callback();

            $record->update(['status_code' => $statusCode]);

            return ['result' => $result, 'audit_record' => $record->toArray()];
        } catch (Throwable $e) {
            $record->update([
                'exception' => $e->getMessage(),
                'status_code' => $e instanceof GovApiException ? $e->httpStatusCode() : null,
            ]);

            if ($e instanceof GovApiException) {
                $e->withAuditContext($record);
            }

            throw $e;
        }
    }

    /**  @throws RuntimeException */
    protected function auditUnit(): string
    {
        $unit = config('govgr.audit.unit');

        if (empty($unit)) {
            throw new RuntimeException(
                'govgr.audit.unit is not configured. Set GOVGR_AUDIT_UNIT to your organization/unit name as registered with the Interoperability Center (ΚΕΔ). It is a mandatory audit field on every ΚΕΔ SOAP call.'
            );
        }

        return $unit;
    }

    /** @throws LogicException */
    protected function identity(): AuditIdentity
    {
        return $this->identity ?? $this->throwStartTransactionNotCalled();
    }

    /** @throws LogicException */
    protected function transactionDate(): Carbon
    {
        return $this->transactionDate ?? $this->throwStartTransactionNotCalled();
    }

    /** @throws LogicException */
    protected function protocol(): string
    {
        return $this->protocol ?? throw new LogicException('logged() must create its AuditRecord before protocol() is available.');
    }

    /**
     * The auditTransactionId value for whichever logged() call is
     * currently in progress. Just the bare AuditRecord id, as a string.
     * Only available once logged() has created its row.
     *
     * @throws LogicException
     */
    protected function transactionId(): string
    {
        return $this->transactionId ?? throw new LogicException('logged() must create its AuditRecord before transactionId() is available.');
    }

    /** The <auditRecord>...</auditRecord> block shared by soap services */
    protected function auditRecordXml(): string
    {
        $protocol = $this->protocol();
        $transactionId = $this->transactionId();
        $date = $this->transactionDate()->format('Y-m-d\TH:i:s\Z');
        $identity = $this->identity();
        $unit = $this->auditUnit();

        return <<<XML
            <auditRecord>
                {$this->tag('auditProtocol', $protocol)}
                {$this->tag('auditTransactionDate', $date)}
                {$this->tag('auditTransactionId', $transactionId)}
                {$this->tag('auditUnit', $unit)}
                {$this->tag('auditUserId', $identity->email)}
                {$this->tagOrEmpty('auditUserIp', $identity->ip)}
            </auditRecord>
            XML;
    }

    /**
     * The same auditRecord fields as auditRecordXml()
     *
     * @return array{auditProtocol: string, auditTransactionDate: string, auditTransactionId: string, auditUnit: string, auditUserId: string, auditUserIp: string}
     */
    protected function auditRecordArray(): array
    {
        $identity = $this->identity();

        return [
            'auditProtocol' => $this->protocol(),
            'auditTransactionDate' => $this->transactionDate()->format('Y-m-d\TH:i:s\Z'),
            'auditTransactionId' => $this->transactionId(),
            'auditUnit' => $this->auditUnit(),
            'auditUserId' => $identity->email,
            'auditUserIp' => $identity->ip ?? '',
        ];
    }

    /** The SOAP 1.1 envelope + WS-Security UsernameToken header shared by every ΚΕΔ SOAP service */
    protected function soapEnvelope(string $namespacePrefix, string $namespaceUri, string $username, string $password, string $operationXml): string
    {
        $username = XmlHelper::escape($username);
        $password = XmlHelper::escape($password);

        return <<<SOAP
            <?xml version="1.0" encoding="UTF-8"?>
            <soapenv:Envelope
                xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                xmlns:$namespacePrefix="$namespaceUri">
                <soapenv:Header>
                    <wsse:Security soapenv:mustUnderstand="1"
                        xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
                        xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
                        <wsse:UsernameToken>
                            <wsse:Username>$username</wsse:Username>
                            <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">$password</wsse:Password>
                        </wsse:UsernameToken>
                    </wsse:Security>
                </soapenv:Header>
                <soapenv:Body>
                    {$operationXml}
                </soapenv:Body>
            </soapenv:Envelope>
            SOAP;
    }

    /**
     * The <{prefix}:{requestElement}>...</...> element: the shared
     * auditRecord block plus the operation-specific input record.
     * $inputRecordXml is the caller's already-escaped field tags (see
     * XmlHelper::tag()/optionalTag()).
     */
    protected function soapRequest(string $namespacePrefix, string $requestElement, string $inputRecordTag, string $inputRecordXml): string
    {
        $audit = $this->auditRecordXml();

        return <<<XML
            <$namespacePrefix:$requestElement>
                {$audit}
                <$inputRecordTag>
                    {$inputRecordXml}
                </$inputRecordTag>
            </$namespacePrefix:$requestElement>
            XML;
    }

    private function tag(string $name, string $value): string
    {
        return XmlHelper::tag($name, $value);
    }

    private function tagOrEmpty(string $name, ?string $value): string
    {
        return XmlHelper::tagOrEmpty($name, $value);
    }

    /** @throws LogicException */
    private function throwStartTransactionNotCalled(string $msg = 'startTransaction() must be called first.'): never
    {
        throw new LogicException($msg);
    }
}
