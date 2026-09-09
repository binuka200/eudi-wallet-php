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

    public function testRejectsInvalidResponseMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RequestOptions(responseMode: 'fragment');
    }

    public function testRedirectTemplateRequiresResponseCodePlaceholder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RequestOptions(redirectUriTemplate: 'https://app.example/callback');
    }

    public function testAcceptsCurrentRequestUriMethods(): void
    {
        foreach (['get', 'post', 'post_get'] as $method) {
            $this->assertSame($method, (new RequestOptions(requestUriMethod: $method))->requestUriMethod);
        }
    }

    public function testRejectsRedirectTemplateWithCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RequestOptions(redirectUriTemplate: 'https://user:pass@app.example/callback?response_code={RESPONSE_CODE}');
    }
}
