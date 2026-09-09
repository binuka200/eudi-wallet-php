<?php

declare(strict_types=1);

namespace EudiWallet;

final class WalletSession
{
    /**
     * @param list<string> $claims
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly string $nonce,
        public readonly array $claims,
        public readonly string $purpose,
        public readonly int $createdAt,
    ) {
        if ($transactionId === '' || $nonce === '') {
            throw new \InvalidArgumentException('Wallet session is missing transaction id or nonce.');
        }
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

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $claims = $data['claims'] ?? [];
        if (!is_array($claims)) {
            throw new \InvalidArgumentException('Wallet session claims must be a list.');
        }

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
