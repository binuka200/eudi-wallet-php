<?php

declare(strict_types=1);

namespace EudiWallet;

/**
 * Maps encoding-independent PID identifiers to their format-specific paths.
 *
 * @see https://github.com/eu-digital-identity-wallet/eudi-doc-attestation-rulebooks-catalog/blob/main/rulebooks/pid/pid-rulebook.md
 */
final class PidAttributeMap
{
    /** @var array<string, string> */
    private const MDOC_NAMES = [
        Claim::FAMILY_NAME => 'family_name',
        Claim::GIVEN_NAME => 'given_name',
        Claim::BIRTH_DATE => 'birth_date',
        Claim::BIRTH_PLACE => 'place_of_birth',
        Claim::NATIONALITY => 'nationality',
        Claim::PORTRAIT => 'portrait',
        Claim::RESIDENT_ADDRESS => 'resident_address',
        Claim::RESIDENT_COUNTRY => 'resident_country',
        Claim::RESIDENT_STATE => 'resident_state',
        Claim::RESIDENT_CITY => 'resident_city',
        Claim::RESIDENT_POSTAL_CODE => 'resident_postal_code',
        Claim::RESIDENT_STREET => 'resident_street',
        Claim::PERSONAL_ADMINISTRATIVE_NUMBER => 'personal_administrative_number',
        Claim::FAMILY_NAME_BIRTH => 'family_name_birth',
        Claim::GIVEN_NAME_BIRTH => 'given_name_birth',
        Claim::SEX => 'sex',
        Claim::EMAIL => 'email_address',
        Claim::MOBILE_PHONE_NUMBER => 'mobile_phone_number',
        Claim::ISSUING_AUTHORITY => 'issuing_authority',
        Claim::ISSUING_COUNTRY => 'issuing_country',
        Claim::EXPIRY_DATE => 'expiry_date',
        Claim::DOCUMENT_NUMBER => 'document_number',
        Claim::ISSUING_JURISDICTION => 'issuing_jurisdiction',
        Claim::ISSUANCE_DATE => 'issuance_date',
        Claim::TRUST_ANCHOR => 'trust_anchor',
        Claim::ATTESTATION_LEGAL_CATEGORY => 'attestation_legal_category',
    ];

    /** @var array<string, list<string>> */
    private const SD_JWT_PATHS = [
        Claim::FAMILY_NAME => ['family_name'],
        Claim::GIVEN_NAME => ['given_name'],
        Claim::BIRTH_DATE => ['birthdate'],
        Claim::BIRTH_PLACE => ['place_of_birth'],
        Claim::NATIONALITY => ['nationalities'],
        Claim::PORTRAIT => ['picture'],
        Claim::RESIDENT_ADDRESS => ['address', 'formatted'],
        Claim::RESIDENT_COUNTRY => ['address', 'country'],
        Claim::RESIDENT_STATE => ['address', 'region'],
        Claim::RESIDENT_CITY => ['address', 'locality'],
        Claim::RESIDENT_POSTAL_CODE => ['address', 'postal_code'],
        Claim::RESIDENT_STREET => ['address', 'street_address'],
        Claim::RESIDENT_HOUSE_NUMBER => ['address', 'house_number'],
        Claim::PERSONAL_ADMINISTRATIVE_NUMBER => ['personal_administrative_number'],
        Claim::FAMILY_NAME_BIRTH => ['birth_family_name'],
        Claim::GIVEN_NAME_BIRTH => ['birth_given_name'],
        Claim::SEX => ['sex'],
        Claim::EMAIL => ['email'],
        Claim::MOBILE_PHONE_NUMBER => ['phone_number'],
        Claim::ISSUING_AUTHORITY => ['issuing_authority'],
        Claim::ISSUING_COUNTRY => ['issuing_country'],
        Claim::EXPIRY_DATE => ['date_of_expiry'],
        Claim::DOCUMENT_NUMBER => ['document_number'],
        Claim::ISSUING_JURISDICTION => ['issuing_jurisdiction'],
        Claim::ISSUANCE_DATE => ['date_of_issuance'],
        Claim::TRUST_ANCHOR => ['trust_anchor'],
        Claim::ATTESTATION_LEGAL_CATEGORY => ['attestation_legal_category'],
    ];

    /** @return list<string> */
    public static function mdocPath(string $claim, string $namespace): array
    {
        if (!self::supportsMdoc($claim)) {
            throw new \InvalidArgumentException('PID attribute is not defined for mdoc: '.$claim);
        }

        return [$namespace, self::MDOC_NAMES[$claim]];
    }

    /** @return list<string> */
    public static function sdJwtPath(string $claim): array
    {
        if (!self::supportsSdJwt($claim)) {
            throw new \InvalidArgumentException('PID attribute is not defined for SD-JWT VC: '.$claim);
        }

        return self::SD_JWT_PATHS[$claim];
    }

    public static function supportsMdoc(string $claim): bool
    {
        return array_key_exists($claim, self::MDOC_NAMES);
    }

    public static function supportsSdJwt(string $claim): bool
    {
        return array_key_exists($claim, self::SD_JWT_PATHS);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public static function normalizeMdoc(array $attributes): array
    {
        $normalized = [];
        foreach (self::MDOC_NAMES as $claim => $name) {
            if (array_key_exists($name, $attributes)) {
                $normalized[$claim] = $attributes[$name];
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function normalizeSdJwt(array $payload): array
    {
        $normalized = [];
        foreach (self::SD_JWT_PATHS as $claim => $path) {
            $found = false;
            $value = self::valueAt($payload, $path, $found);
            if ($found) {
                $normalized[$claim] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $path
     */
    private static function valueAt(array $payload, array $path, bool &$found): mixed
    {
        $value = $payload;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $found = false;

                return null;
            }
            $value = $value[$segment];
        }
        $found = true;

        return $value;
    }
}
