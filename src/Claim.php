<?php

declare(strict_types=1);

namespace EudiWallet;

/**
 * PID attributes commonly requested from an EUDI Wallet.
 *
 * These names match the EU Person Identification Data attribute set used by
 * both mdoc (`eu.europa.ec.eudi.pid.1`) and SD-JWT VC (`urn:eudi:pid:1`).
 */
final class Claim
{
    public const FAMILY_NAME = 'family_name';
    public const GIVEN_NAME = 'given_name';
    public const BIRTH_DATE = 'birth_date';
    public const AGE_OVER_18 = 'age_over_18';
    public const AGE_OVER_21 = 'age_over_21';
    public const NATIONALITY = 'nationality';
    public const BIRTH_PLACE = 'birth_place';
    public const RESIDENT_ADDRESS = 'resident_address';
    public const RESIDENT_COUNTRY = 'resident_country';
    public const RESIDENT_STATE = 'resident_state';
    public const RESIDENT_CITY = 'resident_city';
    public const RESIDENT_POSTAL_CODE = 'resident_postal_code';
    public const RESIDENT_STREET = 'resident_street';
    public const DOCUMENT_NUMBER = 'document_number';
    public const EXPIRY_DATE = 'expiry_date';
    public const ISSUING_AUTHORITY = 'issuing_authority';
    public const ISSUING_COUNTRY = 'issuing_country';
    public const SEX = 'sex';
    public const EMAIL = 'email_address';
    public const PHONE = 'phone_number';

    /** @return list<string> */
    public static function known(): array
    {
        return [
            self::FAMILY_NAME,
            self::GIVEN_NAME,
            self::BIRTH_DATE,
            self::AGE_OVER_18,
            self::AGE_OVER_21,
            self::NATIONALITY,
            self::BIRTH_PLACE,
            self::RESIDENT_ADDRESS,
            self::RESIDENT_COUNTRY,
            self::RESIDENT_STATE,
            self::RESIDENT_CITY,
            self::RESIDENT_POSTAL_CODE,
            self::RESIDENT_STREET,
            self::DOCUMENT_NUMBER,
            self::EXPIRY_DATE,
            self::ISSUING_AUTHORITY,
            self::ISSUING_COUNTRY,
            self::SEX,
            self::EMAIL,
            self::PHONE,
        ];
    }

    public static function isKnown(string $claim): bool
    {
        return in_array($claim, self::known(), true);
    }
}
