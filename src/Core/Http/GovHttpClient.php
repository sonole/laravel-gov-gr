<?php

namespace Sonole\GovGr\Core\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GovHttpClient
{
    public function __construct(
        private readonly int $retries = 2,
        private readonly int $retryDelayMs = 200,
        private readonly int $timeoutSeconds = 15,
    ) {
    }

    public function request(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->retry($this->retries, $this->retryDelayMs, throw: false);
    }

    /**
     * @throws ConnectionException
     */
    public function postXml(string $endpoint, string $body): Response
    {
        return $this->request()
            ->withHeaders(['Content-Type' => 'text/xml; charset=UTF-8'])
            ->withBody($body, 'text/xml; charset=UTF-8')
            ->post($endpoint);
    }
}
