<?php

declare(strict_types=1);

namespace EudiWallet\Dcql;

use EudiWallet\Claim;
use EudiWallet\Exception\UnknownClaim;
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
    public function build(array $claims, string $purpose, string $format = RequestOptions::FORMAT_BOTH): array
    {
        if ($claims === []) {
            throw new \InvalidArgumentException('At least one claim is required.');
        }

        $mdocClaims = [];
        $sdJwtClaims = [];
        foreach ($claims as $claim) {
            if ($claim === '' || !Claim::isKnown($claim)) {
                throw new UnknownClaim('Unsupported EUDI PID claim: '.$claim);
            }
            $mdocClaims[] = ['path' => [self::MDOC_DOCTYPE, $claim]];
            $sdJwtClaims[] = ['path' => [$claim]];
        }

        $credentials = [];
        $options = [];

        if ($format === RequestOptions::FORMAT_BOTH || $format === RequestOptions::FORMAT_MDOC) {
            $credentials[] = [
                'id' => self::MDOC_ID,
                'format' => 'mso_mdoc',
                'meta' => ['doctype_value' => self::MDOC_DOCTYPE],
                'claims' => $mdocClaims,
            ];
            $options[] = [self::MDOC_ID];
        }

        if ($format === RequestOptions::FORMAT_BOTH || $format === RequestOptions::FORMAT_SD_JWT) {
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
                    'purpose' => $purpose,
                ],
            ],
        ];
    }
}
