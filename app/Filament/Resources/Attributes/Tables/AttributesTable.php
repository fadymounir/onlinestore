<?php

namespace App\Filament\Resources\Attributes\Tables;

use App\Enums\AttributeType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AttributesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الخصيصة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('المعرّف')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('type')
                    ->label('طريقة العرض')
                    ->badge()
                    ->formatStateUsing(fn (AttributeType $state): string => $state->label())
                    ->color(fn (AttributeType $state): string => $state === AttributeType::Color ? 'info' : 'gray'),

                TextColumn::make('values_count')
                    ->label('عدد القيم')
                    ->counts('values')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'warning' : 'success'),

                TextColumn::make('products_count')
                    ->label('مستخدمة في')
                    ->counts('products')
                    ->badge()
                    ->color('gray')
                    ->suffix(' منتج'),

                ToggleColumn::make('is_active')
                    ->label('مُفعّلة'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('طريقة العرض')
                    ->options(AttributeType::options()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Model $record): void {
                        // Deleting cascades to values and to the variant pivots,
                        // which would silently break existing variants.
                        if ($record->products()->exists()) {
                            Notification::make()
                                ->title('لا يمكن حذف الخصيصة')
                                ->body('مستخدمة في '.$record->products()->count().' منتج. أزِلها من المنتجات أولاً.')
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
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
