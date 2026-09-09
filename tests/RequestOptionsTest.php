<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\RequestOptions;
use PHPUnit\Framework\TestCase;

final class RequestOptionsTest extends TestCase
{
    public function testRejectsShortNonce(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RequestOptions(nonce: 'short');
    }

    public function testIntendedUseAndCertificateAreExclusive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RequestOptions(intendedUseId: '1', registrationCertificate: 'header.payload.sig');
    }
}
