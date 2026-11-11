<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Models\Governorate;
use App\Models\Order;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OrderForm
{
    /**
     * Deliberately narrow. Totals, items, coupon and status are never edited
     * by hand: totals are a financial record, and status must go through
     * Order::transitionTo() so the audit trail is written. Only the delivery
     * details and the internal note are correctable here.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ملاحظة داخلية')
                    ->description('لا تظهر للعميل.')
                    ->schema([
                        Textarea::make('admin_note')
                            ->label('ملاحظة')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('تصحيح بيانات التوصيل')
                    // An order taken while customer addresses were switched
                    // off arrives here empty on purpose — the shop rings for
                    // the address and fills it in. Say so, rather than
                    // leaving required fields blank with no explanation.
                    ->description(fn (?Order $record): string => $record?->lacksAddress()
                        ? 'هذا الطلب وصل بدون عنوان. اكتب بيانات التوصيل هنا، ثم اضغط «احتساب الشحن من المحافظة» بالأعلى لتسعير الشحن.'
                        : 'للتصحيح قبل الشحن فقط — بعد الشحن تُقفل هذه الحقول.')
                    ->schema([
                        TextInput::make('recipient_name')
                            ->label('المستلم')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('هاتف التوصيل')
                            ->required()
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('alternate_phone')
                            ->label('هاتف بديل')
                            ->tel()
                            ->maxLength(20),

                        Select::make('governorate_id')
                            ->label('المحافظة')
                            ->relationship('governorate', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            // Keep the snapshot text in step with the FK.
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('governorate_name', Governorate::find($state)?->name);
                            }),

                        TextInput::make('governorate_name')
                            ->label('اسم المحافظة المحفوظ')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('city_name')
                            ->label('المدينة')
                            ->maxLength(255),

                        TextInput::make('street')
                            ->label('الشارع')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('building')->label('العقار')->maxLength(255),
                        TextInput::make('floor')->label('الدور')->maxLength(50),
                        TextInput::make('apartment')->label('الشقة')->maxLength(50),

                        TextInput::make('landmark')
                            ->label('علامة مميّزة')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->disabled(fn (?Order $record): bool => $record?->status->isFinal()
                        || $record?->status === OrderStatus::Shipped),
            ]);
    }
}
