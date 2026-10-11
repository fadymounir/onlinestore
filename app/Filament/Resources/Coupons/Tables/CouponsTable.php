<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Enums\CouponType;
use App\Models\Coupon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Enums\FontWeight;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('الكود')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(FontWeight::Bold)
                    ->badge()
                    ->color('primary'),

                TextColumn::make('value')
                    ->label('الخصم')
                    ->formatStateUsing(fn ($state, Coupon $record): string => $record->type === CouponType::Percent
                        ? rtrim(rtrim(number_format((float) $state, 2), '0'), '.').'%'
                        : number_format((float) $state, 2).' ج.م')
                    ->description(fn (Coupon $record): ?string => $record->max_discount
                        ? 'بحد أقصى '.number_format((float) $record->max_discount, 0).' ج.م'
                        : null),

                TextColumn::make('min_order_total')
                    ->label('حد أدنى')
                    ->money('EGP')
                    ->placeholder('بلا حد'),

                TextColumn::make('used_count')
                    ->label('الاستخدام')
                    ->badge()
                    ->formatStateUsing(fn (int $state, Coupon $record): string => $record->usage_limit
                        ? "{$state} / {$record->usage_limit}"
                        : (string) $state)
                    ->color(fn (int $state, Coupon $record): string => $record->usage_limit && $state >= $record->usage_limit
                        ? 'danger'
                        : 'gray')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('ينتهي في')
                    ->dateTime('Y-m-d')
                    ->placeholder('بلا انتهاء')
                    ->description(fn (Coupon $record): ?string => $record->expires_at?->isPast()
                        ? 'منتهي'
                        : $record->expires_at?->diffForHumans())
                    ->sortable(),

                TextColumn::make('state')
                    ->label('الحالة')
                    ->badge()
                    ->state(fn (Coupon $record): string => match (true) {
                        ! $record->is_active => 'موقوف',
                        $record->expires_at && $record->expires_at->isPast() => 'منتهي',
                        $record->starts_at && $record->starts_at->isFuture() => 'لم يبدأ',
                        $record->usage_limit && $record->used_count >= $record->usage_limit => 'نفد',
                        default => 'ساري',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ساري' => 'success',
                        'لم يبدأ' => 'info',
                        default => 'danger',
                    }),

                ToggleColumn::make('is_active')
                    ->label('مُفعّل'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('نوع الخصم')
                    ->options(CouponType::options()),

                TernaryFilter::make('is_active')
                    ->label('التفعيل')
                    ->placeholder('الكل')
                    ->trueLabel('مُفعّل')
                    ->falseLabel('موقوف'),

                Filter::make('live')
                    ->label('سارية الآن')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('is_active', true)
                        ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                        ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))),

                Filter::make('expired')
                    ->label('منتهية')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<', now())),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Model $record): void {
                        // coupon_redemptions cascades: deleting a used coupon
                        // would wipe the discount audit trail for past orders.
                        if ($record->redemptions()->exists()) {
                            Notification::make()
                                ->title('لا يمكن حذف الكوبون')
                                ->body('استُخدم في '.$record->redemptions()->count().' طلب. أوقفه بدلاً من حذفه للحفاظ على سجل الخصومات.')
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
            ->defaultSort('created_at', 'desc');
    }
}
