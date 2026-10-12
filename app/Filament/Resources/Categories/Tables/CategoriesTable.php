<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
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

/**
 * Flat, searchable view of the categories. Structure and ordering live on the
 * tree page — a nested set has no single "sort" column to drag here.
 */
class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Nested-set order: pre-order traversal, so children follow parents.
            ->modifyQueryUsing(fn (Builder $query) => $query->withDepth()->defaultOrder())
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
                    ->description(fn (Category $record): string => $record->parent
                        ? 'تحت: '.$record->parent->name
                        : 'تصنيف رئيسي'),

                TextColumn::make('depth')
                    ->label('المستوى')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (int $state): string => 'مستوى '.($state + 1)),

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
                        true: fn (Builder $query) => $query->whereIsRoot(),
                        false: fn (Builder $query) => $query->hasParent(),
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
                    // products.category_id is restrictOnDelete, and deleting a
                    // node takes its whole subtree with it — so check the
                    // subtree instead of letting the database throw.
                    ->before(function (DeleteAction $action, Model $record): void {
                        if (($products = $record->subtreeProductsCount()) > 0) {
                            Notification::make()
                                ->title('لا يمكن حذف التصنيف')
                                ->body("يوجد {$products} منتج في هذا التصنيف أو في تصنيفاته الفرعية. انقل المنتجات أولاً.")
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
            ]);
    }
}
