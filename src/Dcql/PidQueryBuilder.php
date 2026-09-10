<?php

declare(strict_types=1);

namespace EudiWallet\Dcql;

use EudiWallet\Claim;
use EudiWallet\Exception\UnknownClaim;
use EudiWallet\PidAttributeMap;
use EudiWallet\RequestOptions;

final class PidQueryBuilder
{
    public const MDOC_DOCTYPE = 'eu.europa.ec.eudi.pid.1';
    public const SD_JWT_VCT = 'urn:eudi:pid:1';
    public const MDOC_ID = 'pid_mdoc';
    public const SD_JWT_ID = 'pid_sd_jwt';

    /**
     * @param list<string> $claims
     * @return array<string, mixed>
     */
    public function build(array $claims, string $format = RequestOptions::FORMAT_BOTH): array
    {
        if ($claims === []) {
            throw new \InvalidArgumentException('At least one claim is required.');
        }
        if (count($claims) !== count(array_unique($claims))) {
            throw new \InvalidArgumentException('PID claims must not contain duplicates.');
        }
        if (!in_array($format, [RequestOptions::FORMAT_BOTH, RequestOptions::FORMAT_MDOC, RequestOptions::FORMAT_SD_JWT], true)) {
            throw new \InvalidArgumentException('Unsupported PID presentation format: '.$format);
        }

        $mdocClaims = [];
        $sdJwtClaims = [];
        $mdocUnsupported = [];
        $sdJwtUnsupported = [];
        foreach ($claims as $claim) {
            if ($claim === '' || !Claim::isKnown($claim)) {
                throw new UnknownClaim('Unsupported EUDI PID claim: '.$claim);
            }
            if (PidAttributeMap::supportsMdoc($claim)) {
                $mdocClaims[] = ['path' => PidAttributeMap::mdocPath($claim, self::MDOC_DOCTYPE)];
            } else {
                $mdocUnsupported[] = $claim;
            }
            if (PidAttributeMap::supportsSdJwt($claim)) {
                $sdJwtClaims[] = ['path' => PidAttributeMap::sdJwtPath($claim)];
            } else {
                $sdJwtUnsupported[] = $claim;
            }
        }

        $includeMdoc = ($format === RequestOptions::FORMAT_BOTH || $format === RequestOptions::FORMAT_MDOC) && $mdocUnsupported === [];
        $includeSdJwt = ($format === RequestOptions::FORMAT_BOTH || $format === RequestOptions::FORMAT_SD_JWT) && $sdJwtUnsupported === [];
        if (!$includeMdoc && !$includeSdJwt) {
            $unsupported = $format === RequestOptions::FORMAT_MDOC ? $mdocUnsupported : $sdJwtUnsupported;
            throw new UnknownClaim('Requested PID attributes are not defined for '.$format.': '.implode(', ', $unsupported));
        }

        $credentials = [];
        $options = [];

        if ($includeMdoc) {
            $credentials[] = [
                'id' => self::MDOC_ID,
                'format' => 'mso_mdoc',
                'meta' => ['doctype_value' => self::MDOC_DOCTYPE],
                'claims' => $mdocClaims,
            ];
            $options[] = [self::MDOC_ID];
        }

        if ($includeSdJwt) {
            $credentials[] = [
                'id' => self::SD_JWT_ID,
                'format' => 'dc+sd-jwt',
                'meta' => ['vct_values' => [self::SD_JWT_VCT]],
                'claims' => $sdJwtClaims,
            ];
            $options[] = [self::SD_JWT_ID];
        }

        return [
            'credentials' => $credentials,
            'credential_sets' => [
                [
                    'options' => $options,
                ],
            ],
        ];
    }
}
