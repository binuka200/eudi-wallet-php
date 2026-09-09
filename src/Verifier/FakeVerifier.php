<?php

declare(strict_types=1);

namespace EudiWallet\Verifier;

use EudiWallet\Contract\StartOptions;
use EudiWallet\Contract\StartedPresentation;
use EudiWallet\Contract\Verifier;
use EudiWallet\Contract\WalletResponse;
use EudiWallet\Exception\InvalidWalletResponse;

/**
 * In-process verifier for tests and local application development.
 * It does not speak OpenID4VP and must not be used in production.
 */
final class FakeVerifier implements Verifier
{
    /** @var array<string, array{status: string, payload: array<string, mixed>}> */
    private array $presentations = [];

    public function start(array $dcqlQuery, StartOptions $options): StartedPresentation
    {
        $transactionId = self::randomId();
        $this->presentations[$transactionId] = [
            'status' => WalletResponse::PENDING,
            'payload' => [],
        ];

        return new StartedPresentation(
            transactionId: $transactionId,
            clientId: 'fake:local',
            requestUri: 'https://verifier.test/wallet/request.jwt/'.$transactionId,
            requestUriMethod: $options->requestUriMethod,
            request: null,
            authorizationRequestScheme: $options->authorizationRequestScheme,
        );
    }

    public function fetch(string $transactionId, ?string $responseCode = null): WalletResponse
    {
        $record = $this->presentations[$transactionId] ?? null;
        if ($record === null || $record['status'] === WalletResponse::PENDING) {
            return WalletResponse::pending();
        }

        return WalletResponse::fromVerifierPayload($record['payload']);
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function complete(string $transactionId, array $claims): void
    {
        if (!isset($this->presentations[$transactionId])) {
            throw new InvalidWalletResponse('Unknown fake presentation.');
        }

        $this->presentations[$transactionId] = [
            'status' => WalletResponse::SUBMITTED,
            'payload' => [
                'vp_token' => [
                    'pid_sd_jwt' => [$this->sdJwt($claims)],
                ],
            ],
        ];
    }

    public function fail(string $transactionId, string $error = 'access_denied', ?string $description = null): void
    {
        if (!isset($this->presentations[$transactionId])) {
            throw new InvalidWalletResponse('Unknown fake presentation.');
        }

        $payload = ['error' => $error];
        if ($description !== null) {
            $payload['error_description'] = $description;
        }

        $this->presentations[$transactionId] = [
            'status' => WalletResponse::FAILED,
            'payload' => $payload,
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function sdJwt(array $claims): string
    {
        $header = self::b64url('{"alg":"none","typ":"dc+sd-jwt"}');
        $payload = self::b64url('{"vct":"urn:eudi:pid:1"}');
        $token = $header.'.'.$payload.'.';
        $disclosures = [];
        foreach ($claims as $name => $value) {
            $disclosures[] = self::b64url(json_encode(['salt', $name, $value], JSON_THROW_ON_ERROR));
        }

        return $token.'~'.implode('~', $disclosures).'~'.$header.'.'.$payload.'.';
    }

    private static function randomId(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    private static function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
