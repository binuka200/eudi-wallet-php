<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Claim;
use EudiWallet\Identity\ClaimNormalizer;
use PHPUnit\Framework\TestCase;

final class ClaimNormalizerTest extends TestCase
{
    public function testReadsNestedJsonAndSdJwt(): void
    {
        $disclosure = rtrim(strtr(base64_encode(json_encode(['salt', Claim::AGE_OVER_18, true], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $sdJwt = 'a.b.c~'.$disclosure.'~';

        $claims = (new ClaimNormalizer())->normalize([
            'pid_mdoc' => [
                ['eu.europa.ec.eudi.pid.1' => [Claim::FAMILY_NAME => 'Dupont']],
            ],
            'pid_sd_jwt' => [$sdJwt],
        ]);

        $this->assertSame('Dupont', $claims[Claim::FAMILY_NAME]);
        $this->assertTrue($claims[Claim::AGE_OVER_18]);
    }

    public function testIgnoresUnknownKeys(): void
    {
        $claims = (new ClaimNormalizer())->normalize(['mystery' => 'nope']);

        $this->assertSame([], $claims);
    }
}
