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
        $digest = rtrim(strtr(base64_encode(hash('sha256', $disclosure, true)), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['_sd' => [$digest]], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $token = 'header.'.$payload.'.sig~'.$disclosure.'~kb.header.sig';

        $this->assertSame([Claim::FAMILY_NAME => 'Dupont'], SdJwtDisclosureParser::claims($token));
    }

    public function testIgnoresAnUnreferencedDisclosure(): void
    {
        $disclosure = rtrim(strtr(base64_encode(json_encode(['salt', 'family_name', 'Mallory'], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode('{"vct":"urn:eudi:pid:1"}'), '+/', '-_'), '=');

        $this->assertSame(['vct' => 'urn:eudi:pid:1'], SdJwtDisclosureParser::claims('a.'.$payload.'.b~'.$disclosure.'~'));
    }

    public function testExpandsNestedObjectAndArrayDisclosures(): void
    {
        $locality = $this->disclosure(['salt-locality', 'locality', 'Paris']);
        $nationality = $this->disclosure(['salt-nationality', 'FR']);
        $address = $this->disclosure([
            'salt-address',
            'address',
            ['_sd' => [$this->digest($locality)]],
        ]);
        $nationalities = $this->disclosure([
            'salt-nationalities',
            'nationalities',
            [['...' => $this->digest($nationality)]],
        ]);
        $payload = rtrim(strtr(base64_encode(json_encode([
            '_sd' => [$this->digest($address), $this->digest($nationalities)],
            '_sd_alg' => 'sha-256',
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $token = 'header.'.$payload.'.sig~'.implode('~', [$locality, $nationality, $address, $nationalities]).'~';

        $this->assertSame(
            ['address' => ['locality' => 'Paris'], 'nationalities' => ['FR']],
            SdJwtDisclosureParser::claims($token),
        );
    }

    public function testIgnoresStringsWithoutDisclosures(): void
    {
        $this->assertSame([], SdJwtDisclosureParser::claims('not-an-sd-jwt'));
    }

    /** @param list<mixed> $value */
    private function disclosure(array $value): string
    {
        return rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function digest(string $disclosure): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $disclosure, true)), '+/', '-_'), '=');
    }
}
