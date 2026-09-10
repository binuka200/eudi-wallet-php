<?php

declare(strict_types=1);

namespace EudiWallet\Identity;

use EudiWallet\Dcql\PidQueryBuilder;
use EudiWallet\Exception\InvalidWalletResponse;
use EudiWallet\PidAttributeMap;

/**
 * Extracts PID attributes from presentations accepted by the remote verifier.
 * This class decodes presentation data; it does not perform cryptography.
 */
final class ClaimNormalizer
{
    /**
     * @param array<string, mixed> $vpToken
     * @return array<string, mixed>
     */
    public function normalize(array $vpToken): array
    {
        /** @var array<string, mixed> $found */
        $found = [];

        $mdocPresentations = $vpToken[PidQueryBuilder::MDOC_ID] ?? [];
        foreach ($this->presentations($mdocPresentations) as $presentation) {
            if (is_string($presentation)) {
                $found = $this->merge($found, PidAttributeMap::normalizeMdoc(MdocDisclosureParser::claims($presentation)));
            } elseif (is_array($presentation)) {
                $found = $this->merge($found, $this->normalizeDecodedMdoc($presentation));
            }
        }

        $sdJwtPresentations = $vpToken[PidQueryBuilder::SD_JWT_ID] ?? [];
        foreach ($this->presentations($sdJwtPresentations) as $presentation) {
            if (is_string($presentation)) {
                $found = $this->merge($found, PidAttributeMap::normalizeSdJwt(SdJwtDisclosureParser::claims($presentation)));
            } elseif (is_array($presentation)) {
                $found = $this->merge($found, PidAttributeMap::normalizeSdJwt($presentation));
            }
        }

        return $found;
    }

    /** @return list<mixed> */
    private function presentations(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }

    /**
     * @param array<string, mixed> $presentation
     * @return array<string, mixed>
     */
    private function normalizeDecodedMdoc(array $presentation): array
    {
        $namespace = $this->findNamespace($presentation);

        return $namespace === null ? [] : PidAttributeMap::normalizeMdoc($namespace);
    }

    /**
     * @param array<array-key, mixed> $node
     * @return array<string, mixed>|null
     */
    private function findNamespace(array $node): ?array
    {
        $namespace = $node[PidQueryBuilder::MDOC_DOCTYPE] ?? null;
        if (is_array($namespace) && !array_is_list($namespace)) {
            return $namespace;
        }
        foreach ($node as $value) {
            if (!is_array($value)) {
                continue;
            }
            $found = $this->findNamespace($value);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $claims
     * @return array<string, mixed>
     */
    private function merge(array $target, array $claims): array
    {
        foreach ($claims as $name => $value) {
            if (!array_key_exists($name, $target)) {
                $target[$name] = $value;
            } elseif ($target[$name] !== $value) {
                throw new InvalidWalletResponse('Presentations disagree on PID attribute: '.$name);
            }
        }

        return $target;
    }
}
