<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Claim;
use EudiWallet\WalletSession;
use PHPUnit\Framework\TestCase;

final class WalletSessionTest extends TestCase
{
    public function testRejectsNonceOutsideOpenId4VpCharacterSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WalletSession('tx', str_repeat(' ', 32), [Claim::FAMILY_NAME], 'Test', time());
    }

    public function testRejectsUnsupportedRestoredClaims(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WalletSession::fromArray([
            'transaction_id' => 'tx',
            'nonce' => str_repeat('n', 32),
            'claims' => ['unexpected_claim'],
            'purpose' => 'Test',
            'created_at' => time(),
        ]);
    }

    public function testDetectsExpiredAndImplausiblyFutureSessions(): void
    {
        $session = new WalletSession('tx', str_repeat('n', 32), [Claim::FAMILY_NAME], 'Test', 1_000);

        $this->assertFalse($session->isExpired(600, 1_600));
        $this->assertTrue($session->isExpired(600, 1_601));
        $this->assertTrue($session->isExpired(600, 900));
    }
}
