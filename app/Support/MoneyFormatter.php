<?php

namespace App\Support;

use InvalidArgumentException;

class MoneyFormatter
{
    public static function formatMinorAmount(int $amountMinor): string
    {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Money amounts must be non-negative minor units.');
        }

        return sprintf('%d.%02d', intdiv($amountMinor, 100), $amountMinor % 100);
    }
}
