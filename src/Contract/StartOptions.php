<?php

declare(strict_types=1);

namespace EudiWallet\Contract;

final class StartOptions
{
    public function __construct(
        public readonly string $nonce,
        public readonly string $purpose,
        public readonly string $profile,
        public readonly string $jarMode,
        public readonly string $requestUriMethod,
        public readonly string $responseMode,
        public readonly string $authorizationRequestScheme,
        public readonly ?string $redirectUriTemplate,
        public readonly ?string $issuerChain,
        public readonly ?string $intendedUseId,
        public readonly ?string $registrationCertificate,
    ) {
    }
}
