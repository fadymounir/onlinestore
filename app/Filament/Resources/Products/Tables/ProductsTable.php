<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('primaryImage.path')
                    ->label('الصورة')
                    ->disk('public')
                    ->height(44)
                    ->width(44)
                    ->defaultImageUrl(null),

                TextColumn::make('name')
                    ->label('اسم المنتج')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Model $record): ?string => $record->brand?->name),

                TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->badge()
                    ->sortable(),

                TextColumn::make('variants_count')
                    ->label('المتغيّرات')
                    ->counts('variants')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'gray')
                    ->tooltip(fn (int $state): ?string => $state === 0
                        ? 'لا يمكن شراء المنتج قبل إضافة متغيّر واحد على الأقل.'
                        : null),

                // Summed in SQL rather than loading every variant row per product.
                TextColumn::make('variants_sum_stock_quantity')
                    ->label('المخزون')
                    ->sum('variants', 'stock_quantity')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null, $state <= 0 => 'danger',
                        $state <= 5 => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (?int $state): string => (string) ($state ?? 0))
                    ->sortable(),

                TextColumn::make('base_price')
                    ->label('السعر')
                    ->money('EGP')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('مُفعّل')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_featured')
                    ->label('مميّز')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('brand')
                    ->label('الماركة')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('مُفعّل')
                    ->falseLabel('موقوف'),

                TernaryFilter::make('is_featured')
                    ->label('مميّز')
                    ->placeholder('الكل')
                    ->trueLabel('مميّز')
                    ->falseLabel('غير مميّز'),

                Filter::make('out_of_stock')
                    ->label('نفد المخزون')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave(
                        'variants',
                        fn (Builder $q) => $q->where('stock_quantity', '>', 0),
                    )),

                Filter::make('without_variants')
                    ->label('بدون متغيّرات')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('variants')),

                TrashedFilter::make()
                    ->label('المحذوفة'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
