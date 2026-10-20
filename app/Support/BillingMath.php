<?php

namespace App\Support;

use InvalidArgumentException;

class BillingMath
{
    public static function cents(string|int $amount): int
    {
        $value = (string) $amount;

        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Expected a decimal amount with at most two places.');
        }

        $negative = str_starts_with($value, '-');
        $parts = explode('.', ltrim($value, '-'));
        $cents = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');

        return $negative ? -$cents : $cents;
    }

    public static function decimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function regularDues(string $area, string $rate): int
    {
        $areaHundredths = self::cents($area);
        $rateCents = self::cents($rate);

        if ($areaHundredths < 0 || $rateCents < 0) {
            throw new InvalidArgumentException('Area and rate cannot be negative.');
        }

        return intdiv($areaHundredths * $rateCents + 50, 100);
    }

    public static function penalty(int $balanceCents, string $percent): int
    {
        if ($balanceCents < 0) {
            throw new InvalidArgumentException('Balance cannot be negative.');
        }

        return intdiv($balanceCents * self::cents($percent) + 5000, 10000);
    }
}
