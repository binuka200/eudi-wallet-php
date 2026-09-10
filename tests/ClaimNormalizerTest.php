<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Claim;
use EudiWallet\Dcql\PidQueryBuilder;
use EudiWallet\Identity\ClaimNormalizer;
use PHPUnit\Framework\TestCase;

final class ClaimNormalizerTest extends TestCase
{
    public function testNormalizesDecodedMdocAndSdJwtWireNames(): void
    {
        $sdJwt = $this->sdJwt([
            'birthdate' => '2000-01-02',
            'nationalities' => ['FR', 'IT'],
            'address' => ['locality' => 'Paris'],
        ]);

        $claims = (new ClaimNormalizer())->normalize([
            PidQueryBuilder::MDOC_ID => [
                [PidQueryBuilder::MDOC_DOCTYPE => ['family_name' => 'Dupont']],
            ],
            PidQueryBuilder::SD_JWT_ID => [$sdJwt],
        ]);

        $this->assertSame('Dupont', $claims[Claim::FAMILY_NAME]);
        $this->assertSame('2000-01-02', $claims[Claim::BIRTH_DATE]);
        $this->assertSame(['FR', 'IT'], $claims[Claim::NATIONALITY]);
        $this->assertSame('Paris', $claims[Claim::RESIDENT_CITY]);
    }

    public function testReadsIssuerSignedElementsFromCompactMdoc(): void
    {
        $inner = hex2bin(
            'a2'.
            '71656c656d656e744964656e746966696572'.
            '6b66616d696c795f6e616d65'.
            '6c656c656d656e7456616c7565'.
            '664475706f6e74',
        );
        $this->assertIsString($inner);
        $taggedItem = $this->cborTag(24, $this->cborBytes($inner));
        $deviceResponse = $this->cborMap([
            'documents' => $this->cborArray([
                $this->cborMap([
                    'issuerSigned' => $this->cborMap([
                        'nameSpaces' => $this->cborMap([
                            PidQueryBuilder::MDOC_DOCTYPE => $this->cborArray([$taggedItem]),
                        ]),
                    ]),
                ]),
            ]),
        ]);
        $compact = rtrim(strtr(base64_encode($deviceResponse), '+/', '-_'), '=');

        $claims = (new ClaimNormalizer())->normalize([
            PidQueryBuilder::MDOC_ID => [$compact],
        ]);

        $this->assertSame('Dupont', $claims[Claim::FAMILY_NAME]);
    }

    public function testRejectsPresentationsThatDisagreeOnAnAttribute(): void
    {
        $this->expectException(\EudiWallet\Exception\InvalidWalletResponse::class);
        $this->expectExceptionMessage('family_name');

        (new ClaimNormalizer())->normalize([
            PidQueryBuilder::MDOC_ID => [[PidQueryBuilder::MDOC_DOCTYPE => ['family_name' => 'Dupont']]],
            PidQueryBuilder::SD_JWT_ID => [$this->sdJwt(['family_name' => 'Mallory'])],
        ]);
    }

    public function testAcceptsPresentationsThatAgree(): void
    {
        $claims = (new ClaimNormalizer())->normalize([
            PidQueryBuilder::MDOC_ID => [[PidQueryBuilder::MDOC_DOCTYPE => ['family_name' => 'Dupont']]],
            PidQueryBuilder::SD_JWT_ID => [$this->sdJwt(['family_name' => 'Dupont'])],
        ]);

        $this->assertSame('Dupont', $claims[Claim::FAMILY_NAME]);
    }

    public function testExposesMdocPortraitBytesAsADataUrl(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0jpeg-bytes";
        $claims = (new ClaimNormalizer())->normalize([
            PidQueryBuilder::MDOC_ID => [[PidQueryBuilder::MDOC_DOCTYPE => ['portrait' => $jpeg]]],
        ]);

        $this->assertSame('data:image/jpeg;base64,'.base64_encode($jpeg), $claims[Claim::PORTRAIT]);
    }

    public function testMalformedCompactMdocIsAnError(): void
    {
        $this->expectException(\EudiWallet\Exception\InvalidWalletResponse::class);
        $this->expectExceptionMessage('could not be decoded');

        (new ClaimNormalizer())->normalize([
            PidQueryBuilder::MDOC_ID => [rtrim(strtr(base64_encode("\x9f\x01"), '+/', '-_'), '=')],
        ]);
    }

    /** @param array<string, mixed> $claims */
    private function sdJwt(array $claims): string
    {
        $disclosures = [];
        $digests = [];
        foreach ($claims as $name => $value) {
            $disclosure = $this->base64Url(json_encode(['salt-'.$name, $name, $value], JSON_THROW_ON_ERROR));
            $disclosures[] = $disclosure;
            $digests[] = $this->base64Url(hash('sha256', $disclosure, true));
        }
        $payload = $this->base64Url(json_encode([
            '_sd' => $digests,
            '_sd_alg' => 'sha-256',
            'vct' => 'urn:eudi:pid:1',
        ], JSON_THROW_ON_ERROR));

        return 'header.'.$payload.'.signature~'.implode('~', $disclosures).'~';
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @param array<string, string> $encodedValues */
    private function cborMap(array $encodedValues): string
    {
        $encoded = $this->cborLength(5, count($encodedValues));
        foreach ($encodedValues as $key => $value) {
            $encoded .= $this->cborText($key).$value;
        }

        return $encoded;
    }

    /** @param list<string> $encodedValues */
    private function cborArray(array $encodedValues): string
    {
        return $this->cborLength(4, count($encodedValues)).implode('', $encodedValues);
    }

    private function cborText(string $value): string
    {
        return $this->cborLength(3, strlen($value)).$value;
    }

    private function cborBytes(string $value): string
    {
        return $this->cborLength(2, strlen($value)).$value;
    }

    private function cborTag(int $tag, string $encodedValue): string
    {
        return $this->cborLength(6, $tag).$encodedValue;
    }

    private function cborLength(int $major, int $value): string
    {
        if ($major < 0 || $major > 7 || $value < 0 || $value > 0xffff) {
            throw new \InvalidArgumentException('Test CBOR value is out of range.');
        }
        if ($value < 24) {
            return chr(($major * 32) + $value);
        }
        if ($value <= 0xff) {
            return chr(($major * 32) + 24).chr($value);
        }

        return chr(($major * 32) + 25).pack('n', $value);
    }
}
