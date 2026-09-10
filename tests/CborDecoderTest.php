<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Identity\CborDecoder;
use EudiWallet\Identity\CborTag;
use PHPUnit\Framework\TestCase;

final class CborDecoderTest extends TestCase
{
    public function testDecodesAllFloatWidths(): void
    {
        $this->assertSame(1.5, CborDecoder::decode(hex2bin('f93e00') ?: ''));
        $this->assertSame(100000.0, CborDecoder::decode(hex2bin('fa47c35000') ?: ''));
        $this->assertSame(1.1, CborDecoder::decode(hex2bin('fb3ff199999999999a') ?: ''));
        $this->assertSame(-4.1, CborDecoder::decode(hex2bin('fbc010666666666666') ?: ''));
    }

    public function testDecodesTaggedDateAndNestedStructures(): void
    {
        $value = CborDecoder::decode(hex2bin('a16162d903ec6a323030302d30312d3032') ?: '');

        $this->assertIsArray($value);
        $this->assertInstanceOf(CborTag::class, $value['b']);
        $this->assertSame(1004, $value['b']->number);
        $this->assertSame('2000-01-02', $value['b']->value);
    }

    public function testRejectsIndefiniteLengthAndTrailingData(): void
    {
        foreach (['9f01ff', '0101', 'fb3ff1'] as $hex) {
            try {
                CborDecoder::decode(hex2bin($hex) ?: '');
                $this->fail('Expected '.$hex.' to be rejected.');
            } catch (\UnexpectedValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
