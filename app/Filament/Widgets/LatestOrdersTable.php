<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

class LatestOrdersTable extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('أحدث الطلبات')
            ->query(Order::query()->with('user')->withCount('items'))
            ->columns([
                TextColumn::make('order_number')
                    ->label('رقم الطلب')
                    ->weight(FontWeight::Medium)
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('العميل')
                    ->description(fn (Order $record): string => $record->phone)
                    ->searchable(),

                TextColumn::make('governorate_name')
                    ->label('المحافظة')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('items_count')
                    ->label('البنود'),

                TextColumn::make('grand_total')
                    ->label('الإجمالي')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->icon(fn (OrderStatus $state): string => $state->icon()),

                TextColumn::make('payment_status')
                    ->label('الدفع')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color()),

                TextColumn::make('placed_at')
                    ->label('التاريخ')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('عرض')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Model $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('placed_at', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
