<?php

declare(strict_types=1);

namespace EudiWallet\Identity;

/**
 * Small, bounded CBOR decoder used only to read an already verified mdoc.
 *
 * @internal
 */
final class CborDecoder
{
    private int $offset = 0;
    private int $items = 0;

    private function __construct(private readonly string $input)
    {
    }

    public static function decode(string $input): mixed
    {
        if ($input === '' || strlen($input) > 8_388_608) {
            throw new \UnexpectedValueException('CBOR input is empty or exceeds 8 MiB.');
        }

        $decoder = new self($input);
        $value = $decoder->item(0);
        if ($decoder->offset !== strlen($input)) {
            throw new \UnexpectedValueException('CBOR input contains trailing data.');
        }

        return $value;
    }

    private function item(int $depth): mixed
    {
        if ($depth > 32 || ++$this->items > 100_000) {
            throw new \UnexpectedValueException('CBOR nesting or item limit exceeded.');
        }

        $initial = $this->byte();
        $major = $initial >> 5;
        $additional = $initial & 0x1f;

        if ($additional === 31) {
            throw new \UnexpectedValueException('Indefinite-length CBOR is not supported; ISO/IEC 18013-5 requires definite-length encoding.');
        }
        if ($major === 7 && $additional === 27) {
            return $this->float64();
        }
        $argument = $this->argument($additional);

        return match ($major) {
            0 => $argument,
            1 => -1 - $argument,
            2 => $this->read($argument),
            3 => $this->read($argument),
            4 => $this->array($argument, $depth + 1),
            5 => $this->map($argument, $depth + 1),
            6 => new CborTag($argument, $this->item($depth + 1)),
            7 => $this->simple($additional, $argument),
            default => throw new \UnexpectedValueException('Unsupported CBOR major type.'),
        };
    }

    private function byte(): int
    {
        if ($this->offset >= strlen($this->input)) {
            throw new \UnexpectedValueException('Unexpected end of CBOR input.');
        }

        return ord($this->input[$this->offset++]);
    }

    private function argument(int $additional): int
    {
        return match (true) {
            $additional < 24 => $additional,
            $additional === 24 => $this->byte(),
            $additional === 25 => $this->unpackInteger('n', 2),
            $additional === 26 => $this->unpackInteger('N', 4),
            $additional === 27 => $this->uint64(),
            default => throw new \UnexpectedValueException('Invalid CBOR additional information.'),
        };
    }

    private function unpackInteger(string $format, int $length): int
    {
        $decoded = unpack($format, $this->read($length));
        $value = $decoded[1] ?? null;
        if (!is_int($value)) {
            throw new \UnexpectedValueException('Unable to decode CBOR integer.');
        }

        return $value;
    }

    private function uint64(): int
    {
        $decoded = unpack('Nhigh/Nlow', $this->read(8));
        $high = $decoded['high'] ?? null;
        $low = $decoded['low'] ?? null;
        if (!is_int($high) || !is_int($low) || $high > 0x7fffffff) {
            throw new \UnexpectedValueException('CBOR integer exceeds PHP integer range.');
        }

        return ($high * 4_294_967_296) + $low;
    }

    private function read(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->input)) {
            throw new \UnexpectedValueException('Unexpected end of CBOR input.');
        }
        $value = substr($this->input, $this->offset, $length);
        $this->offset += $length;

        return $value;
    }

    /** @return list<mixed> */
    private function array(int $length, int $depth): array
    {
        $value = [];
        for ($index = 0; $index < $length; ++$index) {
            $value[] = $this->item($depth);
        }

        return $value;
    }

    /** @return array<int|string, mixed> */
    private function map(int $length, int $depth): array
    {
        $value = [];
        for ($index = 0; $index < $length; ++$index) {
            $key = $this->item($depth);
            if (!is_int($key) && !is_string($key)) {
                throw new \UnexpectedValueException('CBOR map key must be an integer or string.');
            }
            $value[$key] = $this->item($depth);
        }

        return $value;
    }

    private function simple(int $additional, int $argument): mixed
    {
        return match ($additional) {
            20 => false,
            21 => true,
            22, 23 => null,
            25 => $this->halfFloat($argument),
            26 => $this->float32($argument),
            default => throw new \UnexpectedValueException('Unsupported CBOR simple value.'),
        };
    }

    private function float32(int $bits): float
    {
        $decoded = unpack('Gvalue', pack('N', $bits));
        if ($decoded === false || !isset($decoded['value']) || !is_float($decoded['value'])) {
            throw new \UnexpectedValueException('Unable to decode CBOR float.');
        }

        return $decoded['value'];
    }

    private function float64(): float
    {
        $decoded = unpack('Evalue', $this->read(8));
        if ($decoded === false || !isset($decoded['value']) || !is_float($decoded['value'])) {
            throw new \UnexpectedValueException('Unable to decode CBOR float.');
        }

        return $decoded['value'];
    }

    private function halfFloat(int $value): float
    {
        $sign = ($value & 0x8000) === 0 ? 1.0 : -1.0;
        $exponent = ($value >> 10) & 0x1f;
        $fraction = $value & 0x03ff;
        if ($exponent === 0) {
            return $sign * (2 ** -14) * ($fraction / 1024);
        }
        if ($exponent === 31) {
            return $fraction === 0 ? $sign * INF : NAN;
        }

        return $sign * (2 ** ($exponent - 15)) * (1 + ($fraction / 1024));
    }
}
