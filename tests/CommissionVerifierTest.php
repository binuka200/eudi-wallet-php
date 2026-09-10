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

    public function testFetchTreatsNotFoundAsPending(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(404, ''));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        $response = $verifier->fetch('tx-1');
        $this->assertSame('pending', $response->status);
        $this->assertSame('https://verifier.example/ui/presentations/tx-1', $transport->sent[0]['url']);
    }

    public function testFetchDoesNotTreatBadRequestAsPending(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueue(new HttpResponse(400, '{"error":"invalid response code"}'));
        $verifier = new CommissionVerifier($transport, 'https://verifier.example');

        $this->expectException(VerifierRejected::class);
        $verifier->fetch('tx-1', 'bad-code');
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

    private function options(string $jarMode = 'by_reference'): StartOptions
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
            registrationCertificate: null,
        );
    }
}
