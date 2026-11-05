<?php

namespace App\Enums;

/**
 * Where a deposit (partial prepayment by bank transfer) stands. Null on the
 * order means it is not a deposit order at all — cash on delivery never
 * carries one of these.
 */
enum DepositStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'إيصال التحويل بانتظار المراجعة',
            self::Approved => 'تم اعتماد العربون',
            self::Rejected => 'إيصال التحويل مرفوض',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Submitted => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-badge',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
