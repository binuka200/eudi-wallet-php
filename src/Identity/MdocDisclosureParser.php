<?php

declare(strict_types=1);

namespace EudiWallet\Identity;

use EudiWallet\Dcql\PidQueryBuilder;

/** Reads issuer-signed data elements from an already verified mdoc DeviceResponse. */
final class MdocDisclosureParser
{
    /** @return array<string, mixed> */
    public static function claims(string $base64UrlDeviceResponse): array
    {
        $binary = self::base64UrlDecode($base64UrlDeviceResponse);
        if ($binary === null) {
            return [];
        }

        try {
            $root = self::unwrapTag(CborDecoder::decode($binary));
        } catch (\UnexpectedValueException) {
            return [];
        }
        if (!is_array($root)) {
            return [];
        }

        $documents = $root['documents'] ?? null;
        if (!is_array($documents)) {
            return [];
        }

        $claims = [];
        foreach ($documents as $document) {
            $document = self::unwrapTag($document);
            if (!is_array($document)) {
                continue;
            }
            $issuerSigned = self::unwrapTag($document['issuerSigned'] ?? null);
            if (!is_array($issuerSigned)) {
                continue;
            }
            $nameSpaces = self::unwrapTag($issuerSigned['nameSpaces'] ?? null);
            if (!is_array($nameSpaces)) {
                continue;
            }
            $items = $nameSpaces[PidQueryBuilder::MDOC_DOCTYPE] ?? null;
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                $decodedItem = self::issuerSignedItem($item);
                if ($decodedItem === null) {
                    continue;
                }
                $identifier = $decodedItem['elementIdentifier'] ?? null;
                if (is_string($identifier) && array_key_exists('elementValue', $decodedItem) && !array_key_exists($identifier, $claims)) {
                    $claims[$identifier] = self::unwrap($decodedItem['elementValue'], 0);
                }
            }
        }

        return $claims;
    }

    /** @return array<string, mixed>|null */
    private static function issuerSignedItem(mixed $item): ?array
    {
        if (!$item instanceof CborTag || $item->number !== 24 || !is_string($item->value)) {
            return null;
        }
        try {
            $decoded = CborDecoder::decode($item->value);
        } catch (\UnexpectedValueException) {
            return null;
        }

        return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
    }

    private static function unwrapTag(mixed $value): mixed
    {
        while ($value instanceof CborTag) {
            $value = $value->value;
        }

        return $value;
    }

    private static function unwrap(mixed $value, int $depth): mixed
    {
        if ($depth > 40) {
            return null;
        }
        $value = self::unwrapTag($value);
        if (!is_array($value)) {
            return $value;
        }
        $unwrapped = [];
        foreach ($value as $key => $item) {
            $unwrapped[$key] = self::unwrap($item, $depth + 1);
        }

        return $unwrapped;
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $padded = strtr($value, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($padded, true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }
}
