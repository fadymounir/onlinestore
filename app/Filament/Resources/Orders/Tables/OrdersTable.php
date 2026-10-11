<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('user.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Order $record): string => $record->phone),

                TextColumn::make('governorate_name')
                    ->label('المحافظة')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('items_count')
                    ->label('البنود')
                    ->counts('items')
                    ->badge(),

                TextColumn::make('grand_total')
                    ->label('الإجمالي')
                    ->money('EGP')
                    ->sortable()
                    ->description(fn (Order $record): ?string => (float) $record->discount_total > 0
                        ? 'خصم '.number_format((float) $record->discount_total, 2).' ج.م'
                        : null),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->icon(fn (OrderStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('الدفع')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('placed_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('حالة الطلب')
                    ->options(OrderStatus::options())
                    ->multiple(),

                SelectFilter::make('payment_status')
                    ->label('حالة الدفع')
                    ->options(PaymentStatus::options())
                    ->multiple(),

                SelectFilter::make('governorate')
                    ->label('المحافظة')
                    ->relationship('governorate', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('coupon')
                    ->label('الكوبون')
                    ->relationship('coupon', 'code')
                    ->preload(),

                Filter::make('placed_between')
                    ->schema([
                        DatePicker::make('from')->label('من تاريخ'),
                        DatePicker::make('until')->label('إلى تاريخ'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('placed_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('placed_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'من '.$data['from'];
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'إلى '.$data['until'];
                        }

                        return $indicators;
                    }),

                Filter::make('needs_action')
                    ->label('بانتظار إجراء')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', [
                        OrderStatus::Pending->value,
                        OrderStatus::Confirmed->value,
                        OrderStatus::Processing->value,
                    ])),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            // No bulk delete: orders are financial records, not rows to tidy up.
            ->toolbarActions([])
            ->defaultSort('placed_at', 'desc');
    }
}
