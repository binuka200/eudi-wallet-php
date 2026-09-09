<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Claim;
use EudiWallet\Dcql\PidQueryBuilder;
use EudiWallet\PidAttributeMap;
use PHPUnit\Framework\TestCase;

final class PidAttributeMapTest extends TestCase
{
    public function testUsesRulebookSpecificWirePaths(): void
    {
        $this->assertSame([PidQueryBuilder::MDOC_DOCTYPE, 'birth_date'], PidAttributeMap::mdocPath(Claim::BIRTH_DATE, PidQueryBuilder::MDOC_DOCTYPE));
        $this->assertSame(['birthdate'], PidAttributeMap::sdJwtPath(Claim::BIRTH_DATE));
        $this->assertSame([PidQueryBuilder::MDOC_DOCTYPE, 'place_of_birth'], PidAttributeMap::mdocPath(Claim::BIRTH_PLACE, PidQueryBuilder::MDOC_DOCTYPE));
        $this->assertSame(['nationalities'], PidAttributeMap::sdJwtPath(Claim::NATIONALITY));
        $this->assertSame(['address', 'locality'], PidAttributeMap::sdJwtPath(Claim::RESIDENT_CITY));
        $this->assertSame(['email'], PidAttributeMap::sdJwtPath(Claim::EMAIL));
        $this->assertSame([PidQueryBuilder::MDOC_DOCTYPE, 'mobile_phone_number'], PidAttributeMap::mdocPath(Claim::MOBILE_PHONE_NUMBER, PidQueryBuilder::MDOC_DOCTYPE));
        $this->assertSame(['phone_number'], PidAttributeMap::sdJwtPath(Claim::MOBILE_PHONE_NUMBER));
        $this->assertSame(['date_of_expiry'], PidAttributeMap::sdJwtPath(Claim::EXPIRY_DATE));
    }

    public function testEveryKnownClaimHasAtLeastOneMapping(): void
    {
        foreach (Claim::known() as $claim) {
            $this->assertTrue(PidAttributeMap::supportsMdoc($claim) || PidAttributeMap::supportsSdJwt($claim));
        }

        $this->assertFalse(PidAttributeMap::supportsMdoc(Claim::RESIDENT_HOUSE_NUMBER));
        $this->assertTrue(PidAttributeMap::supportsSdJwt(Claim::RESIDENT_HOUSE_NUMBER));
    }
}
