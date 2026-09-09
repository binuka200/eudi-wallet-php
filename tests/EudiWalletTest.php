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
            [Claim::AGE_OVER_18, Claim::FAMILY_NAME],
            new RequestOptions(purpose: 'Age verification'),
        );

        $this->assertStringStartsWith('openid4vp://?', $challenge->walletUri);
        $this->assertNull($wallet->poll($challenge->session));

        $verifier->complete($challenge->transactionId(), [
            Claim::AGE_OVER_18 => true,
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
}
