<?php

declare(strict_types=1);

namespace EudiWallet\Contract;

final class WalletResponse
{
    public const PENDING = 'pending';
    public const SUBMITTED = 'submitted';
    public const FAILED = 'failed';

    /**
     * @param array<string, mixed>|null $vpToken
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $status,
        public readonly ?array $vpToken = null,
        public readonly ?string $error = null,
        public readonly ?string $errorDescription = null,
        public readonly array $raw = [],
    ) {
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromVerifierPayload(array $payload): self
    {
        $error = isset($payload['error']) && is_string($payload['error']) && $payload['error'] !== ''
            ? $payload['error']
            : null;
        if ($error !== null) {
            $description = isset($payload['error_description']) && is_string($payload['error_description'])
                ? $payload['error_description']
                : null;

            return new self(self::FAILED, null, $error, $description, $payload);
        }

        $vpToken = $payload['vp_token'] ?? null;
        if (!is_array($vpToken)) {
            return new self(self::FAILED, null, 'invalid_response', 'Verifier returned no vp_token.', $payload);
        }

        return new self(self::SUBMITTED, $vpToken, null, null, $payload);
    }
}
