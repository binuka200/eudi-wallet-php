<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Claim;
use EudiWallet\Dcql\PidQueryBuilder;
use EudiWallet\Exception\UnknownClaim;
use EudiWallet\RequestOptions;
use PHPUnit\Framework\TestCase;

final class PidQueryBuilderTest extends TestCase
{
    public function testBuildsAlternativeMdocAndSdJwtQueries(): void
    {
        $query = (new PidQueryBuilder())->build(
            [Claim::BIRTH_DATE, Claim::RESIDENT_CITY],
            'Identity check',
        );

        $this->assertSame(PidQueryBuilder::MDOC_ID, $query['credentials'][0]['id']);
        $this->assertSame('mso_mdoc', $query['credentials'][0]['format']);
        $this->assertSame(
            [PidQueryBuilder::MDOC_DOCTYPE, 'birth_date'],
            $query['credentials'][0]['claims'][0]['path'],
        );
        $this->assertSame('dc+sd-jwt', $query['credentials'][1]['format']);
        $this->assertSame(['birthdate'], $query['credentials'][1]['claims'][0]['path']);
        $this->assertSame(['address', 'locality'], $query['credentials'][1]['claims'][1]['path']);
        $this->assertSame(
            [[PidQueryBuilder::MDOC_ID], [PidQueryBuilder::SD_JWT_ID]],
            $query['credential_sets'][0]['options'],
        );
        $this->assertArrayNotHasKey('purpose', $query['credential_sets'][0]);
    }

    public function testCanRequestMdocOnly(): void
    {
        $query = (new PidQueryBuilder())->build([Claim::GIVEN_NAME], 'Name', RequestOptions::FORMAT_MDOC);

        $this->assertCount(1, $query['credentials']);
        $this->assertSame('mso_mdoc', $query['credentials'][0]['format']);
    }

    public function testBothFallsBackToSdJwtForSdJwtOnlyAttribute(): void
    {
        $query = (new PidQueryBuilder())->build([Claim::RESIDENT_HOUSE_NUMBER], 'Address');

        $this->assertCount(1, $query['credentials']);
        $this->assertSame(PidQueryBuilder::SD_JWT_ID, $query['credentials'][0]['id']);
        $this->assertSame(['address', 'house_number'], $query['credentials'][0]['claims'][0]['path']);
        $this->assertSame([[PidQueryBuilder::SD_JWT_ID]], $query['credential_sets'][0]['options']);
    }

    public function testRejectsAttributeUnavailableInExplicitFormat(): void
    {
        $this->expectException(UnknownClaim::class);
        (new PidQueryBuilder())->build([Claim::RESIDENT_HOUSE_NUMBER], 'Address', RequestOptions::FORMAT_MDOC);
    }

    public function testRejectsUnknownClaims(): void
    {
        $this->expectException(UnknownClaim::class);
        (new PidQueryBuilder())->build(['not_a_pid_claim'], 'Nope');
    }

    public function testRejectsDuplicateClaims(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PidQueryBuilder())->build([Claim::GIVEN_NAME, Claim::GIVEN_NAME], 'Name');
    }
}
