<?php

declare(strict_types=1);

namespace EudiWallet\Contract;

/**
 * Remote OpenID4VP verifier. Cryptographic verification happens here, not in PHP.
 */
interface Verifier
{
    /**
     * @param array<string, mixed> $dcqlQuery
     */
    public function start(array $dcqlQuery, StartOptions $options): StartedPresentation;

    public function fetch(string $transactionId, ?string $responseCode = null): WalletResponse;
}
