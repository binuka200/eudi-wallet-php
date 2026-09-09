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

    public function ageOver18(): bool
    {
        return $this->booleanClaim(Claim::AGE_OVER_18) === true;
    }

    public function ageOver21(): bool
    {
        return $this->booleanClaim(Claim::AGE_OVER_21) === true;
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

    private function booleanClaim(string $name): ?bool
    {
        $value = $this->claims[$name] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 'true' || $value === '1' || $value === 1) {
            return true;
        }
        if ($value === 'false' || $value === '0' || $value === 0) {
            return false;
        }

        return null;
    }
}
