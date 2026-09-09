<?php

declare(strict_types=1);

namespace EudiWallet;

final class RequestOptions
{
    public const FORMAT_BOTH = 'both';
    public const FORMAT_MDOC = 'mdoc';
    public const FORMAT_SD_JWT = 'sd-jwt';

    public const PROFILE_OPENID4VP = 'openid4vp';
    public const PROFILE_HAIP = 'haip';

    public function __construct(
        public readonly string $purpose = 'Verify identity',
        public readonly string $format = self::FORMAT_BOTH,
        public readonly string $profile = self::PROFILE_OPENID4VP,
        public readonly string $jarMode = 'by_reference',
        public readonly string $requestUriMethod = 'post',
        public readonly string $responseMode = 'direct_post.jwt',
        public readonly string $authorizationRequestScheme = 'openid4vp',
        public readonly ?string $redirectUriTemplate = null,
        public readonly ?string $issuerChain = null,
        public readonly ?string $intendedUseId = null,
        public readonly ?string $registrationCertificate = null,
        public readonly ?string $nonce = null,
    ) {
        if ($this->purpose === '') {
            throw new \InvalidArgumentException('Presentation purpose cannot be empty.');
        }
        if (!in_array($this->format, [self::FORMAT_BOTH, self::FORMAT_MDOC, self::FORMAT_SD_JWT], true)) {
            throw new \InvalidArgumentException('Format must be both, mdoc, or sd-jwt.');
        }
        if (!in_array($this->profile, [self::PROFILE_OPENID4VP, self::PROFILE_HAIP], true)) {
            throw new \InvalidArgumentException('Profile must be openid4vp or haip.');
        }
        if (!in_array($this->jarMode, ['by_reference', 'by_value'], true)) {
            throw new \InvalidArgumentException('jar_mode must be by_reference or by_value.');
        }
        if ($this->nonce !== null && strlen($this->nonce) < 32) {
            throw new \InvalidArgumentException('Nonce must contain at least 32 characters.');
        }
        if ($this->intendedUseId !== null && $this->registrationCertificate !== null) {
            throw new \InvalidArgumentException('intended_use_id and registration_certificate are mutually exclusive.');
        }
    }
}
