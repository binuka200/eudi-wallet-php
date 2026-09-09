<?php

declare(strict_types=1);

namespace EudiWallet;

/**
 * Encoding-independent PID data identifiers from the EU PID Rulebook.
 *
 * These identifiers are deliberately not wire claim names. Several attributes
 * use different paths in mdoc and SD-JWT VC; PidAttributeMap owns that mapping.
 */
final class Claim
{
    public const FAMILY_NAME = 'family_name';
    public const GIVEN_NAME = 'given_name';
    public const BIRTH_DATE = 'birth_date';
    public const BIRTH_PLACE = 'birth_place';
    public const NATIONALITY = 'nationality';
    public const PORTRAIT = 'portrait';
    public const RESIDENT_ADDRESS = 'resident_address';
    public const RESIDENT_COUNTRY = 'resident_country';
    public const RESIDENT_STATE = 'resident_state';
    public const RESIDENT_CITY = 'resident_city';
    public const RESIDENT_POSTAL_CODE = 'resident_postal_code';
    public const RESIDENT_STREET = 'resident_street';
    public const RESIDENT_HOUSE_NUMBER = 'resident_house_number';
    public const PERSONAL_ADMINISTRATIVE_NUMBER = 'personal_administrative_number';
    public const FAMILY_NAME_BIRTH = 'family_name_birth';
    public const GIVEN_NAME_BIRTH = 'given_name_birth';
    public const SEX = 'sex';
    public const EMAIL = 'email_address';
    public const MOBILE_PHONE_NUMBER = 'mobile_phone_number';
    /** @deprecated Use MOBILE_PHONE_NUMBER. */
    public const PHONE = self::MOBILE_PHONE_NUMBER;
    public const ISSUING_AUTHORITY = 'issuing_authority';
    public const ISSUING_COUNTRY = 'issuing_country';
    public const EXPIRY_DATE = 'expiry_date';
    public const DOCUMENT_NUMBER = 'document_number';
    public const ISSUING_JURISDICTION = 'issuing_jurisdiction';
    public const ISSUANCE_DATE = 'issuance_date';
    public const TRUST_ANCHOR = 'trust_anchor';
    public const ATTESTATION_LEGAL_CATEGORY = 'attestation_legal_category';

    /** @return list<string> */
    public static function known(): array
    {
        return [
            self::FAMILY_NAME,
            self::GIVEN_NAME,
            self::BIRTH_DATE,
            self::BIRTH_PLACE,
            self::NATIONALITY,
            self::PORTRAIT,
            self::RESIDENT_ADDRESS,
            self::RESIDENT_COUNTRY,
            self::RESIDENT_STATE,
            self::RESIDENT_CITY,
            self::RESIDENT_POSTAL_CODE,
            self::RESIDENT_STREET,
            self::RESIDENT_HOUSE_NUMBER,
            self::PERSONAL_ADMINISTRATIVE_NUMBER,
            self::FAMILY_NAME_BIRTH,
            self::GIVEN_NAME_BIRTH,
            self::SEX,
            self::EMAIL,
            self::MOBILE_PHONE_NUMBER,
            self::ISSUING_AUTHORITY,
            self::ISSUING_COUNTRY,
            self::EXPIRY_DATE,
            self::DOCUMENT_NUMBER,
            self::ISSUING_JURISDICTION,
            self::ISSUANCE_DATE,
            self::TRUST_ANCHOR,
            self::ATTESTATION_LEGAL_CATEGORY,
        ];
    }

    public static function isKnown(string $claim): bool
    {
        return in_array($claim, self::known(), true);
    }
}
