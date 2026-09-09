<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Exception\VerifierUnavailable;
use EudiWallet\Verifier\Psr18Transport;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class Psr18TransportTest extends TestCase
{
    public function testConvertsPsrRequestAndResponse(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $body = $this->createStub(StreamInterface::class);
        $responseBody = $this->createStub(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('{"ok":true}');

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects($this->once())->method('createRequest')->with('POST', 'https://verifier.example/path')->willReturn($request);
        $request->expects($this->exactly(2))->method('withHeader')->willReturnSelf();
        $request->expects($this->once())->method('withBody')->with($body)->willReturnSelf();

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->expects($this->once())->method('createStream')->with('{}')->willReturn($body);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(201);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getHeaders')->willReturn(['X-Request-Id' => ['request-1']]);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $transport = new Psr18Transport($client, $requestFactory, $streamFactory);
        $result = $transport->send('POST', 'https://verifier.example/path', [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], '{}');

        $this->assertSame(201, $result->status);
        $this->assertSame('{"ok":true}', $result->body);
        $this->assertSame('request-1', $result->header('x-request-id'));
    }

    public function testWrapsClientFailures(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $requestFactory = $this->createStub(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('network down'));

        $transport = new Psr18Transport($client, $requestFactory, $this->createStub(StreamFactoryInterface::class));

        $this->expectException(VerifierUnavailable::class);
        $transport->send('GET', 'https://verifier.example/path');
    }

    public function testWrapsRequestFactoryFailures(): void
    {
        $requestFactory = $this->createStub(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willThrowException(new \RuntimeException('invalid URI'));
        $transport = new Psr18Transport(
            $this->createStub(ClientInterface::class),
            $requestFactory,
            $this->createStub(StreamFactoryInterface::class),
        );

        $this->expectException(VerifierUnavailable::class);
        $transport->send('GET', 'https://verifier.example/path');
    }
}
