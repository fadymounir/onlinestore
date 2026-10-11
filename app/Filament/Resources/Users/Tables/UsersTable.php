<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\OrderStatus;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Enums\FontWeight;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->description(fn (User $record): string => $record->email),

                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('addresses_count')
                    ->label('العناوين')
                    ->counts('addresses')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('orders_count')
                    ->label('الطلبات')
                    ->counts('orders')
                    ->badge()
                    ->sortable(),

                // Lifetime value, summed in SQL over delivered orders only.
                TextColumn::make('orders_sum_grand_total')
                    ->label('إجمالي الشراء')
                    ->sum([
                        'orders' => fn (Builder $query) => $query->where('status', OrderStatus::Delivered->value),
                    ], 'grand_total')
                    ->money('EGP')
                    ->placeholder('0')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('نشط'),

                TextColumn::make('last_login_at')
                    ->label('آخر دخول')
                    ->since()
                    ->placeholder('لم يدخل')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('نشط')
                    ->falseLabel('موقوف'),

                Filter::make('has_orders')
                    ->label('لديه طلبات')
                    ->query(fn (Builder $query): Builder => $query->whereHas('orders')),

                Filter::make('no_orders')
                    ->label('بلا طلبات')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('orders')),

                TrashedFilter::make()->label('المحذوفون'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('أرشفة'),
                RestoreAction::make()->label('استعادة'),
                ForceDeleteAction::make()
                    ->label('حذف نهائي')
                    ->before(function (ForceDeleteAction $action, Model $record): void {
                        // orders.user_id is restrictOnDelete — a hard delete would
                        // hit a foreign key error and orphan the sales history.
                        if ($record->orders()->exists()) {
                            Notification::make()
                                ->title('لا يمكن الحذف النهائي')
                                ->body('العميل لديه '.$record->orders()->count().' طلب. الأرشفة تخفيه دون المساس بسجل المبيعات.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('أرشفة المحدّد'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
