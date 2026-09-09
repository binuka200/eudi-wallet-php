<?php

declare(strict_types=1);

namespace EudiWallet\Verifier;

use EudiWallet\Contract\HttpResponse;
use EudiWallet\Contract\Transport;
use EudiWallet\Exception\VerifierUnavailable;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Psr18Transport implements Transport
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        try {
            $request = $this->requestFactory->createRequest($method, $url);
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
            if ($body !== null) {
                $request = $request->withBody($this->streamFactory->createStream($body));
            }
            $response = $this->client->sendRequest($request);
            $responseHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                if ($values !== []) {
                    $responseHeaders[$name] = $values[0];
                }
            }

            return new HttpResponse(
                $response->getStatusCode(),
                (string) $response->getBody(),
                $responseHeaders,
            );
        } catch (\Throwable $exception) {
            throw new VerifierUnavailable('PSR-18 verifier request failed.', 0, $exception);
        }
    }
}
