<?php

declare(strict_types=1);

namespace EudiWallet;

final class WalletSession
{
    /** @var non-empty-list<string> */
    public readonly array $claims;

    /**
     * @param array<array-key, mixed> $claims
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly string $nonce,
        array $claims,
        public readonly string $purpose,
        public readonly int $createdAt,
    ) {
        if (trim($transactionId) === '') {
            throw new \InvalidArgumentException('Wallet session is missing a transaction id.');
        }
        if (strlen($nonce) < 32) {
            throw new \InvalidArgumentException('Wallet session nonce must contain at least 32 characters.');
        }
        if ($claims === [] || !array_is_list($claims)) {
            throw new \InvalidArgumentException('Wallet session must contain a non-empty claim list.');
        }
        foreach ($claims as $claim) {
            if (!is_string($claim) || !Claim::isKnown($claim)) {
                throw new \InvalidArgumentException('Wallet session contains an unsupported PID claim.');
            }
        }
        if (count($claims) !== count(array_unique($claims))) {
            throw new \InvalidArgumentException('Wallet session claims must not contain duplicates.');
        }
        if (trim($purpose) === '') {
            throw new \InvalidArgumentException('Wallet session purpose cannot be empty.');
        }
        if ($createdAt < 1) {
            throw new \InvalidArgumentException('Wallet session creation time is invalid.');
        }
        $this->claims = $claims;
    }

    /** @return array{transaction_id: string, nonce: string, claims: list<string>, purpose: string, created_at: int} */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'nonce' => $this->nonce,
            'claims' => $this->claims,
            'purpose' => $this->purpose,
            'created_at' => $this->createdAt,
        ];
    }

    public function isExpired(int $lifetimeSeconds, ?int $now = null): bool
    {
        if ($lifetimeSeconds < 1) {
            throw new \InvalidArgumentException('Session lifetime must be at least one second.');
        }
        $now ??= time();

        return $this->createdAt > $now + 60 || $this->createdAt + $lifetimeSeconds < $now;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $claims = $data['claims'] ?? [];
        if (!is_array($claims)) {
            throw new \InvalidArgumentException('Wallet session claims must be a list.');
        }

        /** @var list<string> $normalized */
        $normalized = [];
        foreach ($claims as $claim) {
            if (!is_string($claim) || $claim === '') {
                throw new \InvalidArgumentException('Wallet session claims must be non-empty strings.');
            }
            $normalized[] = $claim;
        }

        $transactionId = $data['transaction_id'] ?? null;
        $nonce = $data['nonce'] ?? null;
        $purpose = $data['purpose'] ?? null;
        $createdAt = $data['created_at'] ?? null;

        if (!is_string($transactionId) || !is_string($nonce) || !is_string($purpose) || !is_int($createdAt)) {
            throw new \InvalidArgumentException('Wallet session payload is incomplete.');
        }

        return new self($transactionId, $nonce, $normalized, $purpose, $createdAt);
    }
}
