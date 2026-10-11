<?php

namespace App\Filament\Resources\Coupons\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RedemptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'redemptions';

    protected static ?string $title = 'سجل الاستخدام';

    /** Read-only: a redemption is the audit record of a real discount. */
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('لم يُستخدم الكوبون بعد')
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('الطلب')
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('العميل')
                    ->searchable()
                    ->description(fn (Model $record): ?string => $record->user?->phone),

                TextColumn::make('discount_amount')
                    ->label('قيمة الخصم')
                    ->money('EGP')
                    ->summarize(Sum::make()->label('إجمالي الخصم')),

                TextColumn::make('order.grand_total')
                    ->label('إجمالي الطلب')
                    ->money('EGP'),

                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label('فتح الطلب')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (Model $record): bool => $record->order !== null)
                    ->url(fn (Model $record): string => OrderResource::getUrl('view', ['record' => $record->order])),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
