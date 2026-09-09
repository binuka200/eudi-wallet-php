<?php

declare(strict_types=1);

namespace EudiWallet;

final class NullObserver implements Contract\Observer
{
    public function record(string $event, array $context = []): void
    {
    }
}
