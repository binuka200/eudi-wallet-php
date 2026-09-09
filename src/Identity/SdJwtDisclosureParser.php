<?php

declare(strict_types=1);

namespace EudiWallet\Identity;

/**
 * Reconstructs disclosed claims from an SD-JWT compact serialization.
 *
 * Signatures, holder binding, validity and trust are verified by the remote
 * verifier. Digest matching is still performed here so only disclosures that
 * belong to the accepted SD-JWT are exposed to the application.
 */
final class SdJwtDisclosureParser
{
    /** @return array<string, mixed> */
    public static function claims(string $compact): array
    {
        $parts = explode('~', $compact);
        $issuerJwt = array_shift($parts);
        if (substr_count($issuerJwt, '.') !== 2) {
            return [];
        }

        $jwtParts = explode('.', $issuerJwt);
        $payload = self::decodeJson($jwtParts[1] ?? '');
        if (!is_array($payload) || array_is_list($payload)) {
            return [];
        }

        $last = $parts[count($parts) - 1] ?? '';
        if ($last !== '' && substr_count($last, '.') === 2) {
            array_pop($parts);
        }

        $algorithm = $payload['_sd_alg'] ?? 'sha-256';
        if (!is_string($algorithm)) {
            return [];
        }
        $hashAlgorithm = match (strtolower($algorithm)) {
            'sha-256' => 'sha256',
            'sha-384' => 'sha384',
            'sha-512' => 'sha512',
            default => null,
        };
        if ($hashAlgorithm === null) {
            return [];
        }

        /** @var array<string, list<mixed>> $disclosures */
        $disclosures = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $decoded = self::decodeJson($part);
            if (!is_array($decoded) || !array_is_list($decoded) || !in_array(count($decoded), [2, 3], true)) {
                continue;
            }
            $digest = self::base64UrlEncode(hash($hashAlgorithm, $part, true));
            $disclosures[$digest] = $decoded;
        }

        $used = [];
        $expanded = self::expand($payload, $disclosures, $used, 0);

        return is_array($expanded) && !array_is_list($expanded) ? $expanded : [];
    }

    /**
     * @param array<string, list<mixed>> $disclosures
     * @param array<string, true> $used
     */
    private static function expand(mixed $node, array $disclosures, array &$used, int $depth): mixed
    {
        if ($depth > 32 || !is_array($node)) {
            return $node;
        }

        if (array_is_list($node)) {
            $expanded = [];
            foreach ($node as $item) {
                if (is_array($item) && count($item) === 1 && isset($item['...']) && is_string($item['...'])) {
                    $digest = $item['...'];
                    $disclosure = $disclosures[$digest] ?? null;
                    if ($disclosure !== null && count($disclosure) === 2 && !isset($used[$digest])) {
                        $used[$digest] = true;
                        $expanded[] = self::expand($disclosure[1], $disclosures, $used, $depth + 1);
                    }

                    continue;
                }
                $expanded[] = self::expand($item, $disclosures, $used, $depth + 1);
            }

            return $expanded;
        }

        $expanded = [];
        foreach ($node as $name => $value) {
            if ($name === '_sd' || $name === '_sd_alg') {
                continue;
            }
            $expanded[$name] = self::expand($value, $disclosures, $used, $depth + 1);
        }

        $digests = $node['_sd'] ?? [];
        if (is_array($digests)) {
            foreach ($digests as $digest) {
                if (!is_string($digest) || isset($used[$digest])) {
                    continue;
                }
                $disclosure = $disclosures[$digest] ?? null;
                if ($disclosure === null || count($disclosure) !== 3) {
                    continue;
                }
                $name = $disclosure[1];
                if (!is_string($name) || $name === '' || array_key_exists($name, $expanded)) {
                    continue;
                }
                $used[$digest] = true;
                $expanded[$name] = self::expand($disclosure[2], $disclosures, $used, $depth + 1);
            }
        }

        return $expanded;
    }

    private static function decodeJson(string $encoded): mixed
    {
        $json = self::base64UrlDecode($encoded);
        if ($json === null || $json === '') {
            return null;
        }
        try {
            return json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $padded = strtr($value, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($padded, true);

        return is_string($decoded) ? $decoded : null;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
