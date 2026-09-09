<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Contract\HttpResponse;
use EudiWallet\Contract\Transport;

final class RecordingTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $sent = [];

    /** @var list<HttpResponse> */
    private array $queue = [];

    public function enqueue(HttpResponse $response): void
    {
        $this->queue[] = $response;
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $this->sent[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];
        $response = array_shift($this->queue);
        if ($response === null) {
            throw new \RuntimeException('No queued HTTP response.');
        }

        return $response;
    }
}
