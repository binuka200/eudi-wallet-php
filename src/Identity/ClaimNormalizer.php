<?php

declare(strict_types=1);

namespace EudiWallet\Identity;

use EudiWallet\Claim;

/**
 * Best-effort extraction of PID attributes from a verifier-returned vp_token.
 *
 * Cryptographic verification is the verifier's job. This parser only reads
 * disclosed JSON and SD-JWT disclosure payloads that the verifier already
 * accepted. Compact mdoc/CBOR presentations stay in the raw vp_token.
 */
final class ClaimNormalizer
{
    /** @var list<string> */
    private array $known;

    public function __construct()
    {
        $this->known = Claim::known();
    }

    /**
     * @param array<string, mixed> $vpToken
     * @return array<string, mixed>
     */
    public function normalize(array $vpToken): array
    {
        $found = [];
        $this->walk($vpToken, $found);

        return $found;
    }

    /**
     * @param array<string, mixed> $found
     */
    private function walk(mixed $node, array &$found): void
    {
        if (is_string($node)) {
            foreach (SdJwtDisclosureParser::claims($node) as $name => $value) {
                $this->remember($found, $name, $value);
            }

            return;
        }

        if (!is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if (is_string($key)) {
                $this->remember($found, $key, $value);
            }
            $this->walk($value, $found);
        }
    }

    /**
     * @param array<string, mixed> $found
     */
    private function remember(array &$found, string $name, mixed $value): void
    {
        if (!in_array($name, $this->known, true)) {
            return;
        }
        if (is_array($value)) {
            return;
        }
        if (!array_key_exists($name, $found)) {
            $found[$name] = $value;
        }
    }
}
