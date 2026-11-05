<?php

namespace App\Enums;

use App\Models\Setting;

/** How the customer settles an order. New cases slot in without a migration. */
enum PaymentMethod: string
{
    case CashOnDelivery = 'cod';
    case Deposit = 'deposit';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'الدفع عند الاستلام',
            self::Deposit => 'عربون مقدّم بتحويل بنكي',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'تدفع نقداً للمندوب وقت تسليم الطلب.',
            self::Deposit => 'تحوّل جزءاً من قيمة الطلب الآن — أو قيمته كاملة — وترفع إيصال التحويل، وتدفع الباقي للمندوب عند الاستلام.',
        };
    }

    /** Deposit orders carry a transfer receipt the admin has to approve. */
    public function requiresReceipt(): bool
    {
        return $this === self::Deposit;
    }

    /** The settings row that switches this method on and off. */
    public function settingKey(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'cod_enabled',
            self::Deposit => 'deposit_enabled',
        };
    }

    public function isEnabled(): bool
    {
        return (bool) Setting::get($this->settingKey(), '0');
    }

    /** Method is off: the message checkout refuses with. */
    public function disabledMessage(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'الدفع عند الاستلام متوقّف حالياً. اختر طريقة دفع أخرى أو تواصل معنا.',
            self::Deposit => 'الدفع بعربون متوقّف حالياً. اختر طريقة دفع أخرى أو تواصل معنا.',
        };
    }

    /**
     * The methods the storefront may offer right now, in display order.
     *
     * @return array<int, self>
     */
    public static function enabled(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method): bool => $method->isEnabled()));
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
