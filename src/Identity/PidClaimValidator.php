<?php

declare(strict_types=1);

namespace EudiWallet\Identity;

use EudiWallet\Claim;
use EudiWallet\Exception\InvalidWalletResponse;

/** Validates normalized PID values against the format-independent Rulebook types. */
final class PidClaimValidator
{
    /**
     * @param array<string, mixed> $claims
     */
    public static function validate(array $claims): void
    {
        $invalid = [];
        foreach ($claims as $claim => $value) {
            if (!self::isValid($claim, $value)) {
                $invalid[] = $claim;
            }
        }

        if ($invalid !== []) {
            throw new InvalidWalletResponse('Verified presentation contains invalid PID attributes: '.implode(', ', $invalid));
        }
    }

    private static function isValid(string $claim, mixed $value): bool
    {
        return match ($claim) {
            Claim::BIRTH_DATE => self::isFullDate($value),
            Claim::EXPIRY_DATE, Claim::ISSUANCE_DATE => self::isFullDate($value) || self::isUtcDateTime($value),
            Claim::BIRTH_PLACE => self::isPlaceOfBirth($value),
            Claim::NATIONALITY => self::isNationalities($value),
            Claim::SEX => is_int($value) && in_array($value, [0, 1, 2, 3, 4, 5, 6, 9], true),
            Claim::RESIDENT_COUNTRY, Claim::ISSUING_COUNTRY => self::isCountryCode($value),
            Claim::EMAIL => self::isText($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            Claim::MOBILE_PHONE_NUMBER => self::isText($value) && preg_match('/^\+[0-9]+$/D', $value) === 1,
            Claim::PORTRAIT => self::isBase64DataUrl($value),
            default => Claim::isKnown($claim) && self::isText($value),
        };
    }

    private static function isText(mixed $value): bool
    {
        return is_string($value) && $value !== '' && preg_match('//u', $value) === 1;
    }

    private static function isBase64DataUrl(mixed $value): bool
    {
        return is_string($value)
            && preg_match('#^data:[a-z0-9.+/-]+(?:;[a-z0-9.+-]+=[a-z0-9.+-]+)*;base64,[A-Za-z0-9+/]+={0,2}$#Di', $value) === 1;
    }

    private static function isCountryCode(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Z]{2}$/D', $value) === 1;
    }

    private static function isFullDate(mixed $value): bool
    {
        return is_string($value) && self::matchesDateFormat($value, 'Y-m-d');
    }

    private static function isUtcDateTime(mixed $value): bool
    {
        return is_string($value) && self::matchesDateFormat($value, 'Y-m-d\TH:i:s\Z');
    }

    private static function matchesDateFormat(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false
            && $date->format($format) === $value
            && (!is_array($errors) || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private static function isNationalities(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            return false;
        }

        foreach ($value as $country) {
            if (!self::isCountryCode($country)) {
                return false;
            }
        }

        return true;
    }

    private static function isPlaceOfBirth(mixed $value): bool
    {
        if (!is_array($value) || array_is_list($value)) {
            return false;
        }

        $found = false;
        foreach (['country', 'region', 'locality'] as $field) {
            if (!array_key_exists($field, $value)) {
                continue;
            }
            $found = true;
            if ($field === 'country' ? !self::isCountryCode($value[$field]) : !self::isText($value[$field])) {
                return false;
            }
        }

        return $found;
    }
}
