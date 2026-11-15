<?php

namespace App\Support;

/**
 * Database SUM() returns ints, floats or decimal strings depending on the driver.
 * Everything sent to the frontend or API is normalized to a two-decimal string.
 */
class Money
{
    public static function format(int|float|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
