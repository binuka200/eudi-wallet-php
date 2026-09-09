<?php

declare(strict_types=1);

namespace EudiWallet\Contract;

interface Observer
{
    /** @param array<string, scalar|null> $context */
    public function record(string $event, array $context = []): void;
}
