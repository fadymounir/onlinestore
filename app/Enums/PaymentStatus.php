<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case PartiallyPaid = 'partial';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'لم يتم الدفع',
            self::PartiallyPaid => 'مدفوع جزئياً (عربون)',
            self::Paid => 'مدفوع',
            self::Failed => 'فشل الدفع',
            self::Refunded => 'مُستَرد',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
