<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Claim;
use EudiWallet\Identity\SdJwtDisclosureParser;
use PHPUnit\Framework\TestCase;

final class SdJwtDisclosureParserTest extends TestCase
{
    public function testReadsObjectPropertyDisclosures(): void
    {
        $disclosure = rtrim(strtr(base64_encode(json_encode(['salt', Claim::FAMILY_NAME, 'Dupont'], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $token = 'header.payload.sig~'.$disclosure.'~kb.header.payload.sig';

        $this->assertSame([Claim::FAMILY_NAME => 'Dupont'], SdJwtDisclosureParser::claims($token));
    }

    public function testIgnoresStringsWithoutDisclosures(): void
    {
        $this->assertSame([], SdJwtDisclosureParser::claims('not-an-sd-jwt'));
    }
}
