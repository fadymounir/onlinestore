<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('الصورة')
                    ->disk('public')
                    ->height(40)
                    ->width(40),

                TextColumn::make('name')
                    ->label('التصنيف')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Model $record): string => $record->parent
                        ? 'تحت: '.$record->parent->name
                        : 'تصنيف رئيسي'),

                TextColumn::make('children_count')
                    ->label('تصنيفات فرعية')
                    ->counts('children')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('products_count')
                    ->label('المنتجات')
                    ->counts('products')
                    ->badge()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('مُفعّل'),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('التصنيف الأب')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('roots')
                    ->label('المستوى')
                    ->placeholder('الكل')
                    ->trueLabel('رئيسية فقط')
                    ->falseLabel('فرعية فقط')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('parent_id'),
                        false: fn (Builder $query) => $query->whereNotNull('parent_id'),
                    ),

                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('مُفعّل')
                    ->falseLabel('موقوف'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // products.category_id is restrictOnDelete, so refuse clearly
                    // instead of letting the database throw.
                    ->before(function (DeleteAction $action, Model $record): void {
                        if ($record->products()->exists()) {
                            Notification::make()
                                ->title('لا يمكن حذف التصنيف')
                                ->body('التصنيف يحتوي على '.$record->products()->count().' منتج. انقل المنتجات أولاً.')
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
