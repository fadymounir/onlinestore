<?php

namespace App\Filament\Resources\ProductVariants\Schemas;

use App\Models\AttributeValue;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('المنتج والتركيبة')
                    ->schema([
                        Select::make('product_id')
                            ->label('المنتج')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            // Changing the product invalidates the chosen values.
                            ->live()
                            ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set) => $set('attributeValues', [])),

                        Select::make('attributeValues')
                            ->label('قيم الخصائص')
                            ->relationship(
                                name: 'attributeValues',
                                titleAttribute: 'value',
                                // Only values belonging to the attributes that the
                                // selected product actually varies by.
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    $productId = $get('product_id');

                                    if (blank($productId)) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    $attributeIds = Product::find($productId)
                                        ?->attributes()
                                        ->pluck('attributes.id') ?? collect();

                                    return $query->whereIn('attribute_id', $attributeIds);
                                },
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (AttributeValue $record): string => $record->attribute->name.': '.$record->value,
                            )
                            ->multiple()
                            ->preload()
                            ->required()
                            ->helperText('اختر المنتج أولاً لتظهر القيم المتاحة له.'),
                    ])
                    ->columns(2),

                Section::make('السعر والمخزون')
                    ->schema([
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('price')
                            ->label('سعر البيع')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ج.م'),

                        TextInput::make('compare_at_price')
                            ->label('السعر قبل الخصم')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ج.م'),

                        TextInput::make('cost_price')
                            ->label('سعر التكلفة')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ج.م'),

                        TextInput::make('stock_quantity')
                            ->label('الكمية المتاحة')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->default(0),

                        TextInput::make('low_stock_threshold')
                            ->label('حد التنبيه')
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->columns(3),

                Section::make('الصورة والحالة')
                    ->schema([
                        FileUpload::make('image')
                            ->label('صورة المتغيّر')
                            ->image()
                            ->imageEditor()
                            ->directory('variants')
                            ->disk('public'),

                        Toggle::make('is_active')
                            ->label('مُفعّل')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
