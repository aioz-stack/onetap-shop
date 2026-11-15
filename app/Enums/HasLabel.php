<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Shared helpers for string-backed enums used as status columns.
 */
trait HasLabel
{
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
