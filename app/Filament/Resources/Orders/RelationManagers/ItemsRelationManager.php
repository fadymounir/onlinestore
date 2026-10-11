<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\OrderItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'بنود الطلب';

    protected static ?string $modelLabel = 'بند';

    protected static ?string $pluralModelLabel = 'البنود';

    /**
     * Read-only on purpose. Line prices and names are a snapshot taken at
     * checkout; editing them here would desync the order totals and rewrite
     * what the customer actually agreed to pay.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                TextColumn::make('product_name')
                    ->label('المنتج')
                    ->weight(FontWeight::Medium)
                    ->description(fn (OrderItem $record): ?string => $record->product_id === null
                        ? 'محذوف من الكتالوج'
                        : null)
                    ->wrap(),

                TextColumn::make('variant_label')
                    ->label('التركيبة')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('unit_price')
                    ->label('سعر الوحدة')
                    ->money('EGP'),

                TextColumn::make('quantity')
                    ->label('الكمية')
                    ->alignCenter(),

                TextColumn::make('line_total')
                    ->label('الإجمالي')
                    ->money('EGP')
                    ->weight(FontWeight::Bold)
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('إجمالي البنود')),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->paginated(false);
    }
}
