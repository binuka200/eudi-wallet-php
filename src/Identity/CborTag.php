<?php

declare(strict_types=1);

namespace EudiWallet\Identity;

/** @internal */
final class CborTag
{
    public function __construct(
        public readonly int $number,
        public readonly mixed $value,
    ) {
    }
}
