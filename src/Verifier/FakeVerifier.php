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
        $wireClaims = [];
        foreach ($claims as $name => $value) {
            self::setPath($wireClaims, \EudiWallet\PidAttributeMap::sdJwtPath($name), $value);
        }
        $disclosures = [];
        $digests = [];
        foreach ($wireClaims as $name => $value) {
            $disclosure = self::b64url(json_encode(['salt-'.$name, $name, $value], JSON_THROW_ON_ERROR));
            $disclosures[] = $disclosure;
            $digests[] = self::b64url(hash('sha256', $disclosure, true));
        }
        $payload = self::b64url(json_encode([
            '_sd' => $digests,
            '_sd_alg' => 'sha-256',
            'vct' => 'urn:eudi:pid:1',
        ], JSON_THROW_ON_ERROR));
        $token = $header.'.'.$payload.'.';

        return $token.'~'.implode('~', $disclosures).'~'.$header.'.'.$payload.'.';
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string> $path
     */
    private static function setPath(array &$target, array $path, mixed $value): void
    {
        $last = array_pop($path);
        if ($last === null) {
            return;
        }
        $cursor = &$target;
        foreach ($path as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        $cursor[$last] = $value;
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
