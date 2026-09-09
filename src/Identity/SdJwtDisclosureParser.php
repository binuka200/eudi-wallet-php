<?php

declare(strict_types=1);

namespace EudiWallet\Identity;

/**
 * Reads object-property disclosures from an SD-JWT compact serialization.
 * Does not verify signatures; only the trusted verifier may supply the token.
 */
final class SdJwtDisclosureParser
{
    /** @return array<string, mixed> */
    public static function claims(string $compact): array
    {
        if (!str_contains($compact, '~')) {
            return [];
        }

        $parts = explode('~', $compact);
        if (count($parts) < 2) {
            return [];
        }

        array_shift($parts);
        $last = $parts[count($parts) - 1] ?? '';
        if ($last !== '' && substr_count($last, '.') === 2) {
            array_pop($parts);
        }

        $claims = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $decoded = self::decode($part);
            if (!is_array($decoded) || count($decoded) !== 3 || !array_is_list($decoded)) {
                continue;
            }
            $name = $decoded[1];
            if (!is_string($name) || $name === '') {
                continue;
            }
            $claims[$name] = $decoded[2];
        }

        return $claims;
    }

    private static function decode(string $part): mixed
    {
        $padded = strtr($part, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        $json = base64_decode($padded, true);
        if (!is_string($json) || $json === '') {
            return null;
        }
        try {
            return json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
