<?php

declare(strict_types=1);

namespace EudiWallet;

final class PresentationChallenge
{
    public function __construct(
        public readonly WalletSession $session,
        public readonly string $walletUri,
        public readonly string $clientId,
        public readonly ?string $requestUri,
    ) {
    }

    public function transactionId(): string
    {
        return $this->session->transactionId;
    }

    /** Same string a QR library should encode for cross-device flows. */
    public function qrPayload(): string
    {
        return $this->walletUri;
    }
}
