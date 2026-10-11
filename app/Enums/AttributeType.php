<?php

namespace App\Enums;

enum AttributeType: string
{
    case Select = 'select';
    case Color = 'color';

    public function label(): string
    {
        return match ($this) {
            self::Select => 'قائمة اختيار',
            self::Color => 'لون',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
