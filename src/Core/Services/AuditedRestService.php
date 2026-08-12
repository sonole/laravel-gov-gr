<?php

namespace Sonole\GovGr\Core\Services;

use Sonole\GovGr\Core\Exceptions\GovApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Throwable;

abstract class AuditedRestService extends BaseGovService
{
    private const AUTH_FAILURE_CODE = 'GEN_AUTHENTICATION_FAILED';

    /**
     * @param  array<string, mixed>  $inputRecord
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    protected function call(string $section, string $operation, array $inputRecord, ?Request $request): array
    {
        $this->startTransaction($request);

        $config = $this->configFor($section);
        $endpoint = $config['endpoint'];
        $inputRecordKey = "{$operation}InputRecord";
        $outputRecordKey = "{$operation}OutputRecord";

        return $this->logged($endpoint, function () use ($config, $endpoint, $inputRecordKey, $inputRecord, $outputRecordKey) {
            $response = $this->http->request()
                ->acceptJson()
                ->asJson()
                ->withBasicAuth($config['username'], $config['password'])
                ->post($endpoint, [
                    'auditRecord' => $this->auditRecordArray(),
                    $inputRecordKey => $inputRecord,
                ]);

            return [$response->status(), $this->unwrap($response, $outputRecordKey)];
        });
    }

    /** @return array{endpoint: string, username: string, password: string} */
    private function configFor(string $section): array
    {
        return $this->credentialsFor($this->environment(), $section);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GovApiException
     */
    private function unwrap(Response $response, string $outputRecordKey): array
    {
        try {
            if (! $response->successful()) {
                throw $this->newException("Request failed with status {$response->status()}.");
            }

            $body = $response->json() ?? [];
            $generalErrorCode = data_get($body, 'errorRecord.errorCode');

            if (! empty($generalErrorCode)) {
                $message = data_get($body, 'errorRecord.errorDescr', $generalErrorCode);

                throw $generalErrorCode === self::AUTH_FAILURE_CODE
                    ? $this->newAuthenticationException($message)
                    : $this->newException($message);
            }

            $record = data_get($body, $outputRecordKey, []);
            $errorCode = data_get($record, 'errorCode');

            if (! empty($errorCode)) {
                throw $this->newException(data_get($record, 'errorDescr', $errorCode));
            }

            return $record;
        } catch (GovApiException $e) {
            throw $e->withHttpStatus($response->status());
        }
    }
}
