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
        ], JSON_THROW_ON_ERROR)));

        $verifier = new CommissionVerifier($transport, 'https://verifier.example', false);
        $started = $verifier->start(['credentials' => []], $this->options());

        $this->assertSame('tx-1', $started->transactionId);
        $this->assertStringContainsString('request_uri=', $started->walletUri());
        $this->assertSame('POST', $transport->sent[0]['method']);
        $this->assertSame('https://verifier.example/ui/presentations', $transport->sent[0]['url']);
        $body = json_decode((string) $transport->sent[0]['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('nonce-value-that-is-long-enough-32', $body['nonce']);
        $this->assertSame('haip', $body['profile']);
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

    private function options(): StartOptions
    {
        return new StartOptions(
            nonce: 'nonce-value-that-is-long-enough-32',
            purpose: 'Test',
            profile: 'haip',
            jarMode: 'by_reference',
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
