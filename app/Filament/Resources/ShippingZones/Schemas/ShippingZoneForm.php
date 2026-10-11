<?php

namespace App\Filament\Resources\ShippingZones\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShippingZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات المنطقة')
                    ->description('المحافظات تُجمَّع في مناطق، والمنطقة هي التي تحمل سعر الشحن.')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم المنطقة')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('القاهرة الكبرى'),

                        TextInput::make('cost')
                            ->label('سعر الشحن')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ج.م'),

                        TextInput::make('free_shipping_threshold')
                            ->label('الشحن مجاناً بعد')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ج.م')
                            ->helperText('اتركه فارغاً لعدم وجود شحن مجاني.'),

                        Toggle::make('is_active')
                            ->label('مُفعّلة')
                            ->default(true),

                        TextInput::make('delivery_days_min')
                            ->label('أقل مدة توصيل')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->suffix('يوم'),

                        TextInput::make('delivery_days_max')
                            ->label('أقصى مدة توصيل')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->suffix('يوم')
                            ->gte('delivery_days_min'),
                    ])
                    ->columns(3),
            ]);
    }
}
