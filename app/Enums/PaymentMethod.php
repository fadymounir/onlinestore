<?php

namespace App\Enums;

/** COD only for v1; new cases slot in without a migration. */
enum PaymentMethod: string
{
    case CashOnDelivery = 'cod';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'الدفع عند الاستلام',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
