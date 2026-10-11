<?php

namespace App\Filament\Resources\ShippingZones\Tables;

use App\Models\ShippingZone;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Enums\FontWeight;

class ShippingZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('المنطقة')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('cost')
                    ->label('سعر الشحن')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('free_shipping_threshold')
                    ->label('شحن مجاني بعد')
                    ->money('EGP')
                    ->placeholder('لا يوجد'),

                TextColumn::make('delivery')
                    ->label('مدة التوصيل')
                    ->state(fn (ShippingZone $record): string => $record->delivery_days_min === $record->delivery_days_max
                        ? $record->delivery_days_min.' يوم'
                        : "من {$record->delivery_days_min} إلى {$record->delivery_days_max} يوم"),

                TextColumn::make('governorates_count')
                    ->label('المحافظات')
                    ->counts('governorates')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'warning' : 'success')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('مُفعّلة'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('مُفعّلة')
                    ->falseLabel('موقوفة'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Model $record): void {
                        // shipping_zone_id is nullOnDelete, so deleting a zone
                        // would leave its governorates with no delivery price
                        // and silently break checkout for them.
                        if ($record->governorates()->exists()) {
                            Notification::make()
                                ->title('لا يمكن حذف المنطقة')
                                ->body('تضم '.$record->governorates()->count().' محافظة. انقلها إلى منطقة أخرى أولاً، وإلا بقيت بلا سعر شحن.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('cost');
    }
}
