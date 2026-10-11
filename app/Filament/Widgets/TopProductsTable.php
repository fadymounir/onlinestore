<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopProductsTable extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading('الأكثر مبيعاً')
            ->description('حسب الكمية المبيعة في الطلبات المُسلَّمة.')
            ->query(
                // Aggregated over the order lines, so a product deleted from
                // the catalogue still shows its historical sales by name.
                OrderItem::query()
                    ->selectRaw('MIN(id) as id, product_name, SUM(quantity) as sold, SUM(line_total) as revenue')
                    ->whereHas('order', fn (Builder $q) => $q->where('status', OrderStatus::Delivered->value))
                    ->groupBy('product_name'),
            )
            ->columns([
                TextColumn::make('product_name')
                    ->label('المنتج')
                    ->weight(FontWeight::Medium)
                    ->wrap(),

                TextColumn::make('sold')
                    ->label('الكمية')
                    ->badge()
                    ->sortable(),

                TextColumn::make('revenue')
                    ->label('الإيرادات')
                    ->money('EGP')
                    ->sortable(),
            ])
            ->defaultSort('sold', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('لا مبيعات مُسلَّمة بعد');
    }
}
