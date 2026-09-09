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
            [Claim::AGE_OVER_18, Claim::FAMILY_NAME],
            'Age check',
        );

        $this->assertSame(PidQueryBuilder::MDOC_ID, $query['credentials'][0]['id']);
        $this->assertSame('mso_mdoc', $query['credentials'][0]['format']);
        $this->assertSame(
            [PidQueryBuilder::MDOC_DOCTYPE, Claim::AGE_OVER_18],
            $query['credentials'][0]['claims'][0]['path'],
        );
        $this->assertSame('dc+sd-jwt', $query['credentials'][1]['format']);
        $this->assertSame([Claim::FAMILY_NAME], $query['credentials'][1]['claims'][1]['path']);
        $this->assertSame(
            [[PidQueryBuilder::MDOC_ID], [PidQueryBuilder::SD_JWT_ID]],
            $query['credential_sets'][0]['options'],
        );
        $this->assertSame('Age check', $query['credential_sets'][0]['purpose']);
    }

    public function testCanRequestMdocOnly(): void
    {
        $query = (new PidQueryBuilder())->build([Claim::GIVEN_NAME], 'Name', RequestOptions::FORMAT_MDOC);

        $this->assertCount(1, $query['credentials']);
        $this->assertSame('mso_mdoc', $query['credentials'][0]['format']);
    }

    public function testRejectsUnknownClaims(): void
    {
        $this->expectException(UnknownClaim::class);
        (new PidQueryBuilder())->build(['not_a_pid_claim'], 'Nope');
    }
}
