<?php

namespace App\Support;

class Money
{
    public static function rupiah(float|int|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Compact form for stat tiles: Rp 1,2 jt / Rp 3,4 M.
     */
    public static function compact(float|int|string|null $amount): string
    {
        $value = (float) $amount;

        return match (true) {
            $value >= 1_000_000_000 => 'Rp '.self::trim($value / 1_000_000_000).' M',
            $value >= 1_000_000 => 'Rp '.self::trim($value / 1_000_000).' jt',
            default => self::rupiah($value),
        };
    }

    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }
}
