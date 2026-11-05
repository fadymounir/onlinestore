<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\DepositStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                    // One line under the total for whichever applies: what the
                    // coupon took off, or what is still to collect on delivery.
                    ->description(function (Order $record): ?string {
                        if ($record->isDeposit()) {
                            return 'عربون '.number_format($record->depositAmount(), 2)
                                .' · متبقي '.number_format($record->remainingAmount(), 2).' ج.م';
                        }

                        return (float) $record->discount_total > 0
                            ? 'خصم '.number_format((float) $record->discount_total, 2).' ج.م'
                            : null;
                    }),

                TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->badge()
                    ->color(fn (Order $record): string => $record->isDeposit() ? 'info' : 'gray')
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label())
                    ->description(fn (Order $record): ?string => $record->deposit_status?->label())
                    ->sortable(),

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
                    // Money the store is still holding on a called-off order.
                    ->description(fn (Order $record): ?string => $record->awaitingRefund()
                        ? 'مستحق استرداد '.number_format($record->refundableAmount(), 2).' ج.م'
                        : null)
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

                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options(PaymentMethod::options())
                    ->multiple(),

                SelectFilter::make('deposit_status')
                    ->label('حالة العربون')
                    ->options(DepositStatus::options())
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

                Filter::make('awaiting_deposit_review')
                    ->label('عربون بانتظار المراجعة')
                    ->query(fn (Builder $query): Builder => $query->depositAwaitingReview()),

                Filter::make('awaiting_refund')
                    ->label('بانتظار استرداد مبلغ')
                    ->query(fn (Builder $query): Builder => $query->owingRefund()),

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
