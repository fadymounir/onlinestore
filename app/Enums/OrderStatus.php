<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Confirmed => 'مؤكد',
            self::Processing => 'قيد التجهيز',
            self::Shipped => 'تم الشحن',
            self::Delivered => 'تم التوصيل',
            self::Cancelled => 'ملغي',
            self::Returned => 'مرتجع',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed, self::Processing => 'info',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::Cancelled, self::Returned => 'danger',
        };
    }

    /**
     * Which statuses this one may legally move to. Keeps the admin from
     * dragging a delivered order back to pending, and drives which action
     * buttons appear on the order page.
     *
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered, self::Returned],
            self::Delivered => [self::Returned],
            self::Cancelled, self::Returned => [],
        };
    }

    public function canMoveTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Confirmed => 'heroicon-o-check-circle',
            self::Processing => 'heroicon-o-cube',
            self::Shipped => 'heroicon-o-truck',
            self::Delivered => 'heroicon-o-hand-thumb-up',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Returned => 'heroicon-o-arrow-uturn-left',
        };
    }

    /** Statuses after which stock has already left the warehouse. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled, self::Returned], true);
    }

    /** @return array<string, string> value => Arabic label, for Filament options. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
