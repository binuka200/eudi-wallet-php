<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Claim;
use EudiWallet\EudiWallet;
use EudiWallet\Exception\PresentationFailed;
use EudiWallet\Exception\PresentationPending;
use EudiWallet\RequestOptions;
use EudiWallet\Verifier\FakeVerifier;
use PHPUnit\Framework\TestCase;

final class EudiWalletTest extends TestCase
{
    public function testRequestAndVerifyThroughFakeVerifier(): void
    {
        $verifier = new FakeVerifier();
        $observer = new RecordingObserver();
        $wallet = new EudiWallet($verifier, $observer);

        $challenge = $wallet->request(
            [Claim::BIRTH_DATE, Claim::FAMILY_NAME],
            new RequestOptions(purpose: 'Identity verification'),
        );

        $this->assertStringStartsWith('openid4vp://?', $challenge->walletUri);
        $this->assertNull($wallet->poll($challenge->session));

        $verifier->complete($challenge->transactionId(), [
            Claim::BIRTH_DATE => '2000-01-01',
            Claim::FAMILY_NAME => 'Dupont',
        ]);

        $identity = $wallet->verify($challenge->session);
        $this->assertTrue($identity->ageOver18());
        $this->assertSame('Dupont', $identity->familyName());
        $this->assertSame($challenge->transactionId(), $identity->transactionId);

        $events = array_column($observer->events, 'event');
        $this->assertContains('presentation.started', $events);
        $this->assertContains('presentation.verified', $events);
        foreach ($observer->events as $event) {
            $encoded = json_encode($event, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('Dupont', $encoded);
        }
    }

    public function testVerifyThrowsWhilePending(): void
    {
        $wallet = new EudiWallet(new FakeVerifier());
        $challenge = $wallet->request([Claim::GIVEN_NAME]);

        $this->expectException(PresentationPending::class);
        $wallet->verify($challenge->session);
    }

    public function testWalletDenialBecomesPresentationFailed(): void
    {
        $verifier = new FakeVerifier();
        $wallet = new EudiWallet($verifier);
        $challenge = $wallet->request([Claim::FAMILY_NAME]);
        $verifier->fail($challenge->transactionId(), 'access_denied', 'User cancelled.');

        $this->expectException(PresentationFailed::class);
        $this->expectExceptionMessage('User cancelled.');
        $wallet->verify($challenge->session);
    }

    public function testSessionRoundTrip(): void
    {
        $wallet = new EudiWallet(new FakeVerifier());
        $challenge = $wallet->request([Claim::BIRTH_DATE]);
        $restored = \EudiWallet\WalletSession::fromArray($challenge->session->toArray());

        $this->assertSame($challenge->session->transactionId, $restored->transactionId);
        $this->assertSame([Claim::BIRTH_DATE], $restored->claims);
    }

    public function testCreatesFreshUrlSafeNonceForEveryRequest(): void
    {
        $wallet = new EudiWallet(new FakeVerifier());

        $first = $wallet->request([Claim::FAMILY_NAME])->session->nonce;
        $second = $wallet->request([Claim::FAMILY_NAME])->session->nonce;

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._~-]{32,}$/D', $first);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._~-]{32,}$/D', $second);
        $this->assertNotSame($first, $second);
    }

    public function testRejectsAResponseMissingARequestedClaim(): void
    {
        $verifier = new FakeVerifier();
        $wallet = new EudiWallet($verifier);
        $challenge = $wallet->request([Claim::FAMILY_NAME, Claim::GIVEN_NAME]);
        $verifier->complete($challenge->transactionId(), [Claim::FAMILY_NAME => 'Dupont']);

        $this->expectException(\EudiWallet\Exception\InvalidWalletResponse::class);
        $wallet->verify($challenge->session);
    }

    public function testRejectsNullRequestedClaim(): void
    {
        $verifier = new FakeVerifier();
        $wallet = new EudiWallet($verifier);
        $challenge = $wallet->request([Claim::FAMILY_NAME]);
        $verifier->complete($challenge->transactionId(), [Claim::FAMILY_NAME => null]);

        $this->expectException(\EudiWallet\Exception\InvalidWalletResponse::class);
        $this->expectExceptionMessage('family_name');
        $wallet->verify($challenge->session);
    }

    public function testRejectsMalformedTypedClaims(): void
    {
        $verifier = new FakeVerifier();
        $wallet = new EudiWallet($verifier);
        $challenge = $wallet->request([Claim::BIRTH_DATE, Claim::NATIONALITY, Claim::SEX]);
        $verifier->complete($challenge->transactionId(), [
            Claim::BIRTH_DATE => '2000-02-31',
            Claim::NATIONALITY => ['France'],
            Claim::SEX => 8,
        ]);

        $this->expectException(\EudiWallet\Exception\InvalidWalletResponse::class);
        $this->expectExceptionMessage('birth_date, nationality, sex');
        $wallet->verify($challenge->session);
    }

    public function testRejectsAnExpiredSessionBeforeCallingVerifier(): void
    {
        $wallet = new EudiWallet(new FakeVerifier(), sessionLifetimeSeconds: 60);
        $session = new \EudiWallet\WalletSession('tx', str_repeat('n', 32), [Claim::FAMILY_NAME], 'Test', 1);

        $this->expectException(\EudiWallet\Exception\PresentationExpired::class);
        $wallet->poll($session);
    }
}
