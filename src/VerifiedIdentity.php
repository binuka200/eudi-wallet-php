<?php

declare(strict_types=1);

namespace EudiWallet;

final class VerifiedIdentity
{
    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed>|null $vpToken
     */
    public function __construct(
        public readonly array $claims,
        public readonly ?array $vpToken,
        public readonly string $transactionId,
    ) {
    }

    /** Returns false when the birth date is absent, partial, invalid, or under 18. */
    public function ageOver18(?\DateTimeImmutable $onDate = null): bool
    {
        return $this->ageAtLeast(18, $onDate) === true;
    }

    /** Returns false when the birth date is absent, partial, invalid, or under 21. */
    public function ageOver21(?\DateTimeImmutable $onDate = null): bool
    {
        return $this->ageAtLeast(21, $onDate) === true;
    }

    public function familyName(): ?string
    {
        return $this->stringClaim(Claim::FAMILY_NAME);
    }

    public function givenName(): ?string
    {
        return $this->stringClaim(Claim::GIVEN_NAME);
    }

    public function birthDate(): ?string
    {
        return $this->stringClaim(Claim::BIRTH_DATE);
    }

    /** @return list<string> */
    public function nationalities(): array
    {
        $value = $this->claims[Claim::NATIONALITY] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $country): bool => is_string($country)));
    }

    public function ageAtLeast(int $years, ?\DateTimeImmutable $onDate = null): ?bool
    {
        if ($years < 0) {
            throw new \InvalidArgumentException('Age must not be negative.');
        }
        $value = $this->birthDate();
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }
        $birthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($birthDate === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }
        $onDate ??= new \DateTimeImmutable('today', new \DateTimeZone('UTC'));

        return $birthDate->modify('+'.$years.' years') <= $onDate;
    }

    public function claim(string $name): mixed
    {
        return $this->claims[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->claims);
    }

    private function stringClaim(string $name): ?string
    {
        $value = $this->claims[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

}
