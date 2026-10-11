<?php

namespace App\Filament\Resources\ProductVariants\Tables;

use App\Models\ProductVariant;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductVariantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('صورة')
                    ->disk('public')
                    ->height(38)
                    ->width(38),

                TextColumn::make('product.name')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ProductVariant $record): ?string => $record->product?->category?->name)
                    ->wrap(),

                TextColumn::make('attributeValues.value')
                    ->label('التركيبة')
                    ->badge()
                    ->separator(','),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextInputColumn::make('price')
                    ->label('السعر')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->sortable(),

                TextInputColumn::make('stock_quantity')
                    ->label('المخزون')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0'])
                    ->sortable(),

                TextColumn::make('stock_state')
                    ->label('الحالة')
                    ->badge()
                    ->state(fn (ProductVariant $record): string => match (true) {
                        $record->stock_quantity <= 0 => 'نفد',
                        $record->isLowStock() => 'قارب على النفاد',
                        default => 'متاح',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'نفد' => 'danger',
                        'قارب على النفاد' => 'warning',
                        default => 'success',
                    }),

                ToggleColumn::make('is_active')
                    ->label('مُفعّل'),
            ])
            ->filters([
                SelectFilter::make('product')
                    ->label('المنتج')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->relationship('product.category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('attributeValues')
                    ->label('قيمة الخصيصة')
                    ->relationship('attributeValues', 'value')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Filter::make('out_of_stock')
                    ->label('نفد المخزون')
                    ->query(fn (Builder $query): Builder => $query->where('stock_quantity', '<=', 0)),

                Filter::make('low_stock')
                    ->label('قارب على النفاد')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('stock_quantity', '>', 0)
                        ->where('low_stock_threshold', '>', 0)
                        ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')),

                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('مُفعّل')
                    ->falseLabel('موقوف'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('setStock')
                        ->label('تعديل المخزون')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->schema([
                            Select::make('mode')
                                ->label('الطريقة')
                                ->options([
                                    'set' => 'تعيين الكمية',
                                    'add' => 'إضافة للكمية الحالية',
                                ])
                                ->default('set')
                                ->required(),

                            TextInput::make('quantity')
                                ->label('الكمية')
                                ->required()
                                ->integer()
                                ->minValue(0),
                        ])
                        ->action(function (array $data, Collection $records): void {
                            foreach ($records as $variant) {
                                $variant->update([
                                    'stock_quantity' => $data['mode'] === 'add'
                                        ? $variant->stock_quantity + (int) $data['quantity']
                                        : (int) $data['quantity'],
                                ]);
                            }

                            Notification::make()
                                ->title('تم تحديث مخزون '.$records->count().' متغيّر')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('adjustPrice')
                        ->label('تعديل السعر')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->schema([
                            Select::make('mode')
                                ->label('الطريقة')
                                ->options([
                                    'set' => 'تعيين سعر موحّد',
                                    'percent' => 'تغيير بنسبة مئوية',
                                ])
                                ->default('percent')
                                ->required(),

                            TextInput::make('amount')
                                ->label('القيمة')
                                ->required()
                                ->numeric()
                                ->helperText('للنسبة: 10 تعني زيادة 10%، و-10 تعني خصم 10%.'),
                        ])
                        ->action(function (array $data, Collection $records): void {
                            foreach ($records as $variant) {
                                $price = $data['mode'] === 'percent'
                                    ? (float) $variant->price * (1 + (float) $data['amount'] / 100)
                                    : (float) $data['amount'];

                                $variant->update(['price' => max(0, round($price, 2))]);
                            }

                            Notification::make()
                                ->title('تم تحديث سعر '.$records->count().' متغيّر')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('product_id');
    }
}
