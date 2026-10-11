<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use App\Support\VariantMatrix;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'المتغيّرات';

    protected static ?string $modelLabel = 'متغيّر';

    protected static ?string $pluralModelLabel = 'المتغيّرات';

    /** Attributes this product varies by, with their values. */
    private function productAttributes()
    {
        return $this->getOwnerRecord()
            ->attributes()
            ->with('values')
            ->orderBy('sort_order')
            ->get();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('التركيبة')
                    ->schema([
                        Select::make('attributeValues')
                            ->label('قيم الخصائص')
                            ->relationship(
                                name: 'attributeValues',
                                titleAttribute: 'value',
                                modifyQueryUsing: fn (Builder $query) => $query->whereIn(
                                    'attribute_id',
                                    $this->getOwnerRecord()->attributes()->pluck('attributes.id'),
                                ),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (AttributeValue $record): string => $record->attribute->name.': '.$record->value,
                            )
                            ->multiple()
                            ->preload()
                            ->required()
                            ->helperText('قيمة واحدة من كل خصيصة، مثل: اللون أحمر + المقاس XL.'),
                    ]),

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
                            ->suffix('ج.م')
                            ->helperText('للتقارير فقط — لا يظهر للعميل.'),

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
                            ->default(0)
                            ->helperText('ينبّهك عند وصول المخزون لهذا الحد. صفر = بلا تنبيه.'),
                    ])
                    ->columns(2),

                Section::make('الصورة والحالة')
                    ->schema([
                        FileUpload::make('image')
                            ->label('صورة المتغيّر')
                            ->image()
                            ->imageEditor()
                            ->directory('variants')
                            ->disk('public')
                            ->helperText('اختيارية — تُستخدم عند اختيار العميل لهذه التركيبة.'),

                        Toggle::make('is_active')
                            ->label('مُفعّل')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->emptyStateHeading('لا متغيّرات بعد')
            ->emptyStateDescription('استخدم «توليد المتغيّرات» لإنشاء كل التركيبات مرة واحدة.')
            ->columns([
                TextColumn::make('attributeValues.value')
                    ->label('التركيبة')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->size(TextSize::Small),

                TextInputColumn::make('price')
                    ->label('السعر')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->sortable(),

                TextInputColumn::make('stock_quantity')
                    ->label('المخزون')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0'])
                    ->sortable(),

                TextColumn::make('stock_state')
                    ->label('الحالة')
                    ->badge()
                    ->state(fn (ProductVariant $record): string => match (true) {
                        $record->stock_quantity <= 0 => 'نفد',
                        $record->isLowStock() => 'قارب على النفاد',
                        default => 'متاح',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'نفد' => 'danger',
                        'قارب على النفاد' => 'warning',
                        default => 'success',
                    }),

                ToggleColumn::make('is_active')
                    ->label('مُفعّل'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('مُفعّل')
                    ->falseLabel('موقوف'),

                Filter::make('out_of_stock')
                    ->label('نفد المخزون')
                    ->query(fn (Builder $query): Builder => $query->where('stock_quantity', '<=', 0)),
            ])
            ->headerActions([
                $this->generateMatrixAction(),

                CreateAction::make()
                    ->label('إضافة متغيّر')
                    ->mutateDataUsing(function (array $data): array {
                        $data['sku'] = $data['sku'] ?: Str::upper(Str::random(8));

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    \Filament\Actions\BulkAction::make('restock')
                        ->label('تعديل المخزون')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->schema([
                            TextInput::make('stock_quantity')
                                ->label('الكمية الجديدة')
                                ->required()
                                ->integer()
                                ->minValue(0),
                        ])
                        ->action(function (array $data, \Illuminate\Support\Collection $records): void {
                            $records->each->update(['stock_quantity' => $data['stock_quantity']]);

                            Notification::make()
                                ->title('تم تحديث مخزون '.$records->count().' متغيّر')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('sku');
    }

    /**
     * Builds every combination of the ticked attribute values in one go.
     * SKUs are derived from the value ids, so running it again after adding a
     * new colour creates only the genuinely new rows.
     */
    public function generateMatrixAction(): Action
    {
        return Action::make('generateMatrix')
            ->label('توليد المتغيّرات')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->color('primary')
            ->modalHeading('توليد المتغيّرات')
            ->modalSubmitActionLabel('توليد')
            ->modalWidth('2xl')
            ->schema(function (): array {
                $product = $this->getOwnerRecord();
                $attributes = $this->productAttributes();

                if ($attributes->isEmpty()) {
                    return [
                        Placeholder::make('no_attributes')
                            ->label('لا خصائص محدّدة')
                            ->content('حدّد خصائص المنتج أولاً من قسم «يتغيّر هذا المنتج حسب» في بيانات المنتج، ثم عُد إلى هنا.'),
                    ];
                }

                $lists = $attributes
                    ->map(fn (Attribute $attribute) => CheckboxList::make("values_{$attribute->id}")
                        ->label($attribute->name)
                        ->options($attribute->values->pluck('value', 'id'))
                        ->columns(4)
                        ->bulkToggleable()
                        ->required())
                    ->all();

                return [
                    ...$lists,

                    Section::make('القيم الافتراضية للمتغيّرات الجديدة')
                        ->schema([
                            TextInput::make('sku_prefix')
                                ->label('بادئة الـ SKU')
                                ->required()
                                ->maxLength(20)
                                ->default(Str::upper(Str::slug($product->slug)))
                                ->rules(['regex:/^[A-Za-z0-9-]+$/']),

                            TextInput::make('price')
                                ->label('السعر')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->suffix('ج.م')
                                ->default($product->base_price),

                            TextInput::make('stock_quantity')
                                ->label('المخزون لكل متغيّر')
                                ->required()
                                ->integer()
                                ->minValue(0)
                                ->default(0),
                        ])
                        ->columns(3),
                ];
            })
            ->action(function (array $data): void {
                $product = $this->getOwnerRecord();
                $attributes = $this->productAttributes();

                if ($attributes->isEmpty()) {
                    return;
                }

                $groups = $attributes
                    ->map(fn (Attribute $a): array => array_map('intval', $data["values_{$a->id}"] ?? []))
                    ->all();

                $combinations = VariantMatrix::combinations($groups);

                $created = 0;
                $skipped = 0;

                DB::transaction(function () use ($product, $combinations, $data, &$created, &$skipped): void {
                    foreach ($combinations as $combination) {
                        $sku = VariantMatrix::sku($data['sku_prefix'], $combination);

                        if (ProductVariant::where('sku', $sku)->exists()) {
                            $skipped++;

                            continue;
                        }

                        $variant = $product->variants()->create([
                            'sku' => $sku,
                            'price' => $data['price'],
                            'stock_quantity' => $data['stock_quantity'],
                        ]);

                        $variant->attributeValues()->attach($combination);

                        $created++;
                    }
                });

                $notification = Notification::make()
                    ->title($created > 0 ? "تم توليد {$created} متغيّراً" : 'لا متغيّرات جديدة')
                    ->body($skipped > 0 ? "تم تجاهل {$skipped} موجوداً مسبقاً." : null);

                $created > 0 ? $notification->success() : $notification->warning();

                $notification->send();
            });
    }
}
