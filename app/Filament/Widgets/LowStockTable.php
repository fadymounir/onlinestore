<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductVariants\ProductVariantResource;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LowStockTable extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('مخزون يحتاج تدخّلاً')
            ->description('متغيّرات نفدت أو قاربت على النفاد — عدّل الكمية من هنا مباشرة.')
            ->query(
                ProductVariant::query()
                    ->with('product', 'attributeValues')
                    ->where('is_active', true)
                    ->where(fn (Builder $q) => $q
                        ->where('stock_quantity', '<=', 0)
                        ->orWhere(fn (Builder $w) => $w
                            ->where('low_stock_threshold', '>', 0)
                            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'))),
            )
            ->columns([
                TextColumn::make('product.name')
                    ->label('المنتج')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('attributeValues.value')
                    ->label('التركيبة')
                    ->badge()
                    ->separator(','),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->copyable()
                    ->searchable(),

                TextInputColumn::make('stock_quantity')
                    ->label('المخزون')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0']),

                TextColumn::make('state')
                    ->label('الحالة')
                    ->badge()
                    ->state(fn (ProductVariant $record): string => $record->stock_quantity <= 0 ? 'نفد' : 'قارب على النفاد')
                    ->color(fn (string $state): string => $state === 'نفد' ? 'danger' : 'warning'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('تعديل')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Model $record): string => ProductVariantResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('stock_quantity')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('كل المخزون في حالة جيدة')
            ->emptyStateDescription('لا توجد متغيّرات نفدت أو قاربت على النفاد.');
    }
}
