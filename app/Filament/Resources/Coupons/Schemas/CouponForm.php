<?php

namespace App\Filament\Resources\Coupons\Schemas;

use App\Enums\CouponType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('الكود ونوع الخصم')
                    ->schema([
                        TextInput::make('code')
                            ->label('كود الكوبون')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            // Customers type this at checkout, so keep it ASCII.
                            ->rules(['regex:/^[A-Z0-9_-]+$/'])
                            ->dehydrateStateUsing(fn (string $state): string => strtoupper($state))
                            ->helperText('حروف إنجليزية كبيرة وأرقام فقط — العميل يكتبه عند الدفع.'),

                        ToggleButtons::make('type')
                            ->label('نوع الخصم')
                            ->options(CouponType::options())
                            ->default(CouponType::Fixed->value)
                            ->inline()
                            ->required()
                            ->live(),

                        TextInput::make('value')
                            ->label(fn (Get $get): string => $get('type') === CouponType::Percent->value
                                ? 'نسبة الخصم'
                                : 'قيمة الخصم')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn (Get $get): ?float => $get('type') === CouponType::Percent->value ? 100 : null)
                            ->suffix(fn (Get $get): string => $get('type') === CouponType::Percent->value ? '%' : 'ج.م')
                            ->live(onBlur: true),

                        Placeholder::make('preview')
                            ->label('مثال')
                            ->content(function (Get $get): string {
                                $value = (float) ($get('value') ?? 0);

                                if ($value <= 0) {
                                    return 'أدخل قيمة الخصم لرؤية المثال.';
                                }

                                $subtotal = 1000.0;

                                $discount = $get('type') === CouponType::Percent->value
                                    ? $subtotal * ($value / 100)
                                    : $value;

                                $cap = $get('max_discount');

                                if (filled($cap)) {
                                    $discount = min($discount, (float) $cap);
                                }

                                $discount = min($discount, $subtotal);

                                return 'على طلب بـ 1000 ج.م يصبح الخصم '
                                    .number_format($discount, 2).' ج.م، والمطلوب '
                                    .number_format($subtotal - $discount, 2).' ج.م.';
                            }),
                    ])
                    ->columns(2),

                Section::make('شروط الاستخدام')
                    ->schema([
                        TextInput::make('min_order_total')
                            ->label('الحد الأدنى للطلب')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ج.م')
                            ->helperText('اتركه فارغاً لعدم وجود حد أدنى.'),

                        TextInput::make('max_discount')
                            ->label('أقصى خصم')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ج.م')
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => $get('type') === CouponType::Percent->value)
                            ->helperText('سقف الخصم للكوبونات بالنسبة المئوية.'),

                        TextInput::make('usage_limit')
                            ->label('إجمالي عدد الاستخدامات')
                            ->integer()
                            ->minValue(1)
                            ->helperText('اتركه فارغاً لاستخدام غير محدود.'),

                        TextInput::make('usage_limit_per_user')
                            ->label('عدد الاستخدامات لكل عميل')
                            ->integer()
                            ->minValue(1),

                        DateTimePicker::make('starts_at')
                            ->label('يبدأ من')
                            ->seconds(false),

                        DateTimePicker::make('expires_at')
                            ->label('ينتهي في')
                            ->seconds(false)
                            ->after('starts_at'),

                        Toggle::make('is_active')
                            ->label('مُفعّل')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
