<?php

namespace App\Filament\Resources\ContentPages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('الصفحة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('الرابط')
                    ->prefix('/page/')
                    ->color('gray')
                    ->copyable()
                    ->searchable(),

                ToggleColumn::make('is_active')
                    ->label('منشورة'),

                ToggleColumn::make('show_in_footer')
                    ->label('في التذييل'),

                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->badge()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('آخر تعديل')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('منشورة')
                    ->falseLabel('موقوفة'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            // The footer's own order, so the table reads the way the site does.
            ->defaultSort('sort_order');
    }
}
