<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Claim;
use EudiWallet\VerifiedIdentity;
use PHPUnit\Framework\TestCase;

final class VerifiedIdentityTest extends TestCase
{
    public function testCalculatesAgeFromBirthDateAtAStableBoundary(): void
    {
        $identity = new VerifiedIdentity([Claim::BIRTH_DATE => '2006-09-10'], null, 'tx');

        $this->assertFalse($identity->ageOver18(new \DateTimeImmutable('2024-09-09')));
        $this->assertTrue($identity->ageOver18(new \DateTimeImmutable('2024-09-10')));
        $this->assertFalse($identity->ageOver21(new \DateTimeImmutable('2024-09-10')));
    }

    public function testReturnsUnknownForAnInvalidOrPartialBirthDate(): void
    {
        $identity = new VerifiedIdentity([Claim::BIRTH_DATE => '2006-09'], null, 'tx');

        $this->assertNull($identity->ageAtLeast(18));
        $this->assertFalse($identity->ageOver18());
    }

    public function testReturnsOnlyStringNationalities(): void
    {
        $identity = new VerifiedIdentity([Claim::NATIONALITY => ['FR', null, 'IT']], null, 'tx');

        $this->assertSame(['FR', 'IT'], $identity->nationalities());
    }
}
