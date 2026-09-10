<?php

declare(strict_types=1);

namespace EudiWallet;

use EudiWallet\Contract\Observer;
use EudiWallet\Contract\StartOptions;
use EudiWallet\Contract\Verifier;
use EudiWallet\Contract\WalletResponse;
use EudiWallet\Dcql\PidQueryBuilder;
use EudiWallet\Exception\InvalidWalletResponse;
use EudiWallet\Exception\PresentationFailed;
use EudiWallet\Exception\PresentationExpired;
use EudiWallet\Exception\PresentationPending;
use EudiWallet\Identity\ClaimNormalizer;
use EudiWallet\Identity\PidClaimValidator;

/**
 * PHP façade over an OpenID4VP verifier. The application owns sessions and users.
 */
final class EudiWallet
{
    private readonly PidQueryBuilder $queryBuilder;
    private readonly ClaimNormalizer $normalizer;

    public function __construct(
        private readonly Verifier $verifier,
        private readonly Observer $observer = new NullObserver(),
        ?PidQueryBuilder $queryBuilder = null,
        ?ClaimNormalizer $normalizer = null,
        private readonly int $sessionLifetimeSeconds = 600,
    ) {
        if ($this->sessionLifetimeSeconds < 1) {
            throw new \InvalidArgumentException('Session lifetime must be at least one second.');
        }
        $this->queryBuilder = $queryBuilder ?? new PidQueryBuilder();
        $this->normalizer = $normalizer ?? new ClaimNormalizer();
    }

    /**
     * @param list<string> $claims
     */
    public function request(array $claims, ?RequestOptions $options = null): PresentationChallenge
    {
        $options ??= new RequestOptions();
        $nonce = self::randomNonce();
        $query = $this->queryBuilder->build($claims, $options->purpose, $options->format);
        $started = $this->verifier->start($query, new StartOptions(
            nonce: $nonce,
            purpose: $options->purpose,
            profile: $options->profile,
            jarMode: $options->jarMode,
            requestUriMethod: $options->requestUriMethod,
            responseMode: $options->responseMode,
            authorizationRequestScheme: $options->authorizationRequestScheme,
            redirectUriTemplate: $options->redirectUriTemplate,
            issuerChain: $options->issuerChain,
            intendedUseId: $options->intendedUseId,
            registrationCertificate: $options->registrationCertificate,
        ));

        $session = new WalletSession(
            transactionId: $started->transactionId,
            nonce: $nonce,
            claims: $claims,
            purpose: $options->purpose,
            createdAt: time(),
        );

        $this->observer->record('presentation.started', [
            'transaction_present' => true,
            'claim_count' => count($claims),
            'profile' => $options->profile,
        ]);

        return new PresentationChallenge(
            session: $session,
            walletUri: $started->walletUri(),
            clientId: $started->clientId,
            requestUri: $started->requestUri,
        );
    }

    public function verify(WalletSession $session, ?string $responseCode = null): VerifiedIdentity
    {
        $identity = $this->poll($session, $responseCode);
        if ($identity === null) {
            throw new PresentationPending('The wallet has not submitted a presentation yet.');
        }

        return $identity;
    }

    public function poll(WalletSession $session, ?string $responseCode = null): ?VerifiedIdentity
    {
        if ($session->isExpired($this->sessionLifetimeSeconds)) {
            throw new PresentationExpired('The wallet presentation session has expired.');
        }
        $response = $this->verifier->fetch($session->transactionId, $responseCode);
        if ($response->status === WalletResponse::PENDING) {
            $this->observer->record('presentation.pending', ['transaction_present' => true]);

            return null;
        }
        if ($response->status === WalletResponse::FAILED) {
            $this->observer->record('presentation.failed', [
                'transaction_present' => true,
                'error' => $response->error,
            ]);
            throw new PresentationFailed($response->errorDescription ?? $response->error ?? 'Wallet presentation failed.');
        }
        if ($response->vpToken === null) {
            throw new InvalidWalletResponse('Verifier returned a submitted presentation without a vp_token.');
        }

        $claims = $this->normalizer->normalize($response->vpToken);
        $requested = array_fill_keys($session->claims, true);
        $missing = array_keys(array_diff_key($requested, $claims));
        if ($missing !== []) {
            throw new InvalidWalletResponse('Verified presentation is missing requested PID attributes: '.implode(', ', $missing));
        }
        $claims = array_intersect_key($claims, $requested);
        PidClaimValidator::validate($claims);
        $this->observer->record('presentation.verified', [
            'transaction_present' => true,
            'disclosed_claim_count' => count($claims),
        ]);

        return new VerifiedIdentity($claims, $response->vpToken, $session->transactionId);
    }

    private static function randomNonce(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
