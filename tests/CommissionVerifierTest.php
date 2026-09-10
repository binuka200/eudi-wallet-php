<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Contract\HttpResponse;
use EudiWallet\Contract\StartOptions;
use EudiWallet\Exception\InvalidConfiguration;
use EudiWallet\Exception\VerifierRejected;
use EudiWallet\Verifier\CommissionVerifier;
use PHPUnit\Framework\TestCase;

final class CommissionVerifierTest extends TestCase
{
    public function testStartPostsDcqlAndParsesTransaction(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(200, json_encode([
            'transaction_id' => 'tx-1',
            'client_id' => 'x509_san_dns:localhost',
            'request_uri' => 'https://verifier.test/wallet/request.jwt/abc',
            'request_uri_method' => 'post',
            'authorization_request_uri' => 'haip-vp://?client_id=server-generated',
        ], JSON_THROW_ON_ERROR)));

        $verifier = new CommissionVerifier($transport, 'https://verifier.example', false);
        $started = $verifier->start(['credentials' => []], $this->options());

        $this->assertSame('tx-1', $started->transactionId);
        $this->assertSame('haip-vp://?client_id=server-generated', $started->walletUri());
        $this->assertSame('POST', $transport->sent[0]['method']);
        $this->assertSame('https://verifier.example/ui/presentations/v2', $transport->sent[0]['url']);
        $body = json_decode((string) $transport->sent[0]['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('nonce-value-that-is-long-enough-32', $body['nonce']);
        $this->assertSame('haip', $body['profile']);
        $this->assertSame('openid4vp', $body['authorization_request_scheme']);
    }

    public function testFetchTreatsBadRequestWithoutResponseCodeAsPending(): void
    {
        // The Commission verifier answers 400 with an empty body until the wallet submits.
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(400, ''));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        $response = $verifier->fetch('tx-1');
        $this->assertSame('pending', $response->status);
        $this->assertSame('https://verifier.example/ui/presentations/tx-1', $transport->sent[0]['url']);
    }

    public function testFetchTreatsBadRequestWithResponseCodeAsRejected(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(400, ''));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        try {
            $verifier->fetch('tx-1', 'bad-code');
            $this->fail('A wrong response code must not look pending.');
        } catch (VerifierRejected $exception) {
            $this->assertSame(400, $exception->status());
        }
    }

    public function testFetchTreatsNotFoundAsUnknownTransaction(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(404, ''));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        try {
            $verifier->fetch('tx-1');
            $this->fail('An unknown transaction must not look pending.');
        } catch (VerifierRejected $exception) {
            $this->assertSame(404, $exception->status());
        }
    }

    public function testRejectionKeepsATruncatedBodyForDiagnostics(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(422, str_repeat('x', VerifierRejected::MAX_BODY_BYTES + 100)));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        try {
            $verifier->start(['credentials' => []], $this->options());
            $this->fail('Expected rejection.');
        } catch (VerifierRejected $exception) {
            $this->assertSame(422, $exception->status());
            $this->assertSame(VerifierRejected::MAX_BODY_BYTES, strlen($exception->responseBody));
            $this->assertStringNotContainsString('xxx', $exception->getMessage());
        }
    }

    public function testStartRejectionSurfacesTheVerifierErrorCode(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(400, '{"error":"MissingRegistrationCertificate"}'));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        try {
            $verifier->start(['credentials' => []], $this->options());
            $this->fail('Expected rejection.');
        } catch (VerifierRejected $exception) {
            $this->assertSame('Verifier rejected the presentation request: MissingRegistrationCertificate', $exception->getMessage());
        }
    }

    public function testStartRejectionIgnoresUnsafeErrorCodes(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(400, '{"error":"<script>alert(1)</script>"}'));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        try {
            $verifier->start(['credentials' => []], $this->options());
            $this->fail('Expected rejection.');
        } catch (VerifierRejected $exception) {
            $this->assertSame('Verifier rejected the presentation request.', $exception->getMessage());
        }
    }

    public function testDefaultIntendedUseIsSentWhenTheRequestHasNone(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue($this->startResponse());
        $verifier = new CommissionVerifier($transport, 'https://verifier.example', intendedUseId: '1');

        $verifier->start(['credentials' => []], $this->options());
        $body = json_decode((string) $transport->sent[0]['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('1', $body['intended_use_id']);
        $this->assertArrayNotHasKey('registration_certificate', $body);
    }

    public function testRequestLevelRegistrationCertificateOverridesTheDefaultIntendedUse(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue($this->startResponse());
        $verifier = new CommissionVerifier($transport, 'https://verifier.example', intendedUseId: '1');

        $verifier->start(['credentials' => []], $this->options(registrationCertificate: 'h.p.s'));
        $body = json_decode((string) $transport->sent[0]['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('h.p.s', $body['registration_certificate']);
        $this->assertArrayNotHasKey('intended_use_id', $body);
    }

    public function testDefaultIntendedUseAndCertificateAreExclusive(): void
    {
        $this->expectException(InvalidConfiguration::class);
        new CommissionVerifier(new RecordingTransport(), 'https://verifier.example', intendedUseId: '1', registrationCertificate: 'h.p.s');
    }

    public function testConfiguredHeadersAreSentWithEveryRequest(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(400, ''));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example', headers: [
            'Authorization' => 'Bearer secret',
            'X-Api-Key' => 'key',
        ]);

        $verifier->fetch('tx-1');
        $this->assertSame('Bearer secret', $transport->sent[0]['headers']['Authorization']);
        $this->assertSame('key', $transport->sent[0]['headers']['X-Api-Key']);
        $this->assertSame('application/json', $transport->sent[0]['headers']['Accept']);
    }

    public function testReservedAndUnsafeHeadersAreRejected(): void
    {
        foreach ([
            ['Accept' => 'text/plain'],
            ['content-type' => 'text/plain'],
            ['Authorization' => "Bearer a\r\nX-Injected: 1"],
            ['Bad Name' => 'x'],
        ] as $headers) {
            try {
                new CommissionVerifier(new RecordingTransport(), 'https://verifier.example', headers: $headers);
                $this->fail('Header set should have been rejected: '.json_encode($headers, JSON_THROW_ON_ERROR));
            } catch (InvalidConfiguration) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testStartRequiresClientId(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(200, json_encode([
            'transaction_id' => 'tx-1',
            'request_uri' => 'https://verifier.test/request',
            'authorization_request_uri' => 'openid4vp://?request_uri=test',
        ], JSON_THROW_ON_ERROR)));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        $this->expectException(\EudiWallet\Exception\InvalidWalletResponse::class);
        $verifier->start(['credentials' => []], $this->options());
    }

    public function testStartOmitsRequestUriMethodForByValueRequest(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(200, json_encode([
            'transaction_id' => 'tx-1',
            'client_id' => 'x509_san_dns:localhost',
            'request' => 'signed-request.jwt',
            'authorization_request_uri' => 'openid4vp://?request=signed-request.jwt',
        ], JSON_THROW_ON_ERROR)));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');
        $options = $this->options(jarMode: 'by_value');

        $started = $verifier->start(['credentials' => []], $options);
        $body = json_decode((string) $transport->sent[0]['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('request_uri_method', $body);
        $this->assertNull($started->requestUriMethod);
    }

    public function testStartRequiresAuthoritativeAuthorizationRequestUri(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(200, json_encode([
            'transaction_id' => 'tx-1',
            'client_id' => 'x509_san_dns:localhost',
            'request_uri' => 'https://verifier.test/request',
            'request_uri_method' => 'post',
        ], JSON_THROW_ON_ERROR)));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        $this->expectException(\EudiWallet\Exception\InvalidWalletResponse::class);
        $verifier->start(['credentials' => []], $this->options());
    }

    public function testStartRejectsNonStandardRequestUriMethodFromVerifier(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(200, json_encode([
            'transaction_id' => 'tx-1',
            'client_id' => 'x509_san_dns:localhost',
            'request_uri' => 'https://verifier.test/request',
            'request_uri_method' => 'post_get',
            'authorization_request_uri' => 'openid4vp://?request_uri=test',
        ], JSON_THROW_ON_ERROR)));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        $this->expectException(\EudiWallet\Exception\InvalidWalletResponse::class);
        $verifier->start(['credentials' => []], $this->options());
    }

    public function testFetchIncludesResponseCode(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(200, json_encode([
            'vp_token' => ['pid_sd_jwt' => ['a.b.c']],
        ], JSON_THROW_ON_ERROR)));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example/');

        $response = $verifier->fetch('tx/1', 'code 1');
        $this->assertSame('submitted', $response->status);
        $this->assertSame(
            'https://verifier.example/ui/presentations/tx%2F1?response_code=code%201',
            $transport->sent[0]['url'],
        );
    }

    public function testHttpIsRejectedUnlessExplicitlyAllowed(): void
    {
        $this->expectException(InvalidConfiguration::class);
        new CommissionVerifier(new RecordingTransport(), 'http://localhost:8080');
    }

    public function testLocalHttpCanBeAllowed(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(500, 'nope'));
        $verifier = new CommissionVerifier($transport, 'http://127.0.0.1:8080', true);

        $this->expectException(VerifierRejected::class);
        $verifier->fetch('tx');
    }

    private function startResponse(): HttpResponse
    {
        return new HttpResponse(200, json_encode([
            'transaction_id' => 'tx-1',
            'client_id' => 'x509_san_dns:localhost',
            'request_uri' => 'https://verifier.test/wallet/request.jwt/abc',
            'request_uri_method' => 'post',
            'authorization_request_uri' => 'openid4vp://?client_id=x&request_uri=y',
        ], JSON_THROW_ON_ERROR));
    }

    private function options(string $jarMode = 'by_reference', ?string $registrationCertificate = null): StartOptions
    {
        return new StartOptions(
            nonce: 'nonce-value-that-is-long-enough-32',
            purpose: 'Test',
            profile: 'haip',
            jarMode: $jarMode,
            requestUriMethod: 'post',
            responseMode: 'direct_post.jwt',
            authorizationRequestScheme: 'openid4vp',
            redirectUriTemplate: 'https://app.example/callback?response_code={RESPONSE_CODE}',
            issuerChain: null,
            intendedUseId: null,
            registrationCertificate: $registrationCertificate,
        );
    }
}
