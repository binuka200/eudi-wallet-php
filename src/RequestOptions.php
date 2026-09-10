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
    ) {
        if (trim($this->purpose) === '') {
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
        if (!in_array($this->requestUriMethod, ['get', 'post'], true)) {
            throw new \InvalidArgumentException('request_uri_method must be get or post.');
        }
        if (!in_array($this->responseMode, ['direct_post', 'direct_post.jwt'], true)) {
            throw new \InvalidArgumentException('response_mode must be direct_post or direct_post.jwt.');
        }
        if (preg_match('/^[a-z][a-z0-9+.-]*$/D', $this->authorizationRequestScheme) !== 1) {
            throw new \InvalidArgumentException('Authorization request scheme is invalid.');
        }
        if ($this->redirectUriTemplate !== null) {
            if (!str_contains($this->redirectUriTemplate, '{RESPONSE_CODE}')) {
                throw new \InvalidArgumentException('Redirect URI template must contain {RESPONSE_CODE}.');
            }
            $templateUrl = str_replace('{RESPONSE_CODE}', 'code', $this->redirectUriTemplate);
            $parts = parse_url($templateUrl);
            $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
            $host = is_array($parts) ? ($parts['host'] ?? null) : null;
            if (!in_array($scheme, ['http', 'https'], true) || !is_string($host) || $host === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
                throw new \InvalidArgumentException('Redirect URI template must be an absolute HTTP(S) URL.');
            }
        }
        if ($this->intendedUseId !== null && $this->registrationCertificate !== null) {
            throw new \InvalidArgumentException('intended_use_id and registration_certificate are mutually exclusive.');
        }
    }
}
