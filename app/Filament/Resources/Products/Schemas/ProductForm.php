<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات المنتج')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم المنتج')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            // Only auto-fill the slug while creating, so editing a
                            // product's name never breaks a URL already in the wild.
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('المعرّف في الرابط')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('يظهر في رابط المنتج. الأحرف اللاتينية والأرقام والشرطات فقط.')
                            ->rules(['regex:/^[a-z0-9-]+$/']),

                        Select::make('category_id')
                            ->label('التصنيف')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->label('الاسم')->required(),
                                TextInput::make('slug')->label('المعرّف')->required()
                                    ->unique('categories', 'slug'),
                            ])
                            ->createOptionUsing(fn (array $data) => \App\Models\Category::create($data)->getKey()),

                        Select::make('brand_id')
                            ->label('الماركة')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->label('الاسم')->required(),
                                TextInput::make('slug')->label('المعرّف')->required()
                                    ->unique('brands', 'slug'),
                            ])
                            ->createOptionUsing(fn (array $data) => \App\Models\Brand::create($data)->getKey()),

                        Textarea::make('short_description')
                            ->label('وصف مختصر')
                            ->maxLength(500)
                            ->rows(2)
                            ->columnSpanFull(),

                        RichEditor::make('description')
                            ->label('الوصف الكامل')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('السعر والعرض')
                    ->description('سعر العرض فقط — السعر الفعلي للشراء يُحدَّد على كل متغيّر.')
                    ->schema([
                        TextInput::make('base_price')
                            ->label('السعر')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ج.م'),

                        TextInput::make('compare_at_price')
                            ->label('السعر قبل الخصم')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('ج.م')
                            ->helperText('يظهر مشطوباً بجانب السعر.')
                            ->gt('base_price'),

                        Toggle::make('is_active')
                            ->label('مُفعّل')
                            ->default(true),

                        Toggle::make('is_featured')
                            ->label('منتج مميّز')
                            ->helperText('يظهر في قسم المميّز بالصفحة الرئيسية.'),

                        DateTimePicker::make('published_at')
                            ->label('تاريخ النشر')
                            ->seconds(false)
                            ->helperText('اتركه فارغاً للنشر فوراً.'),
                    ])
                    ->columns(2),

                Section::make('الصور')
                    ->schema([
                        Repeater::make('images')
                            ->label('صور المنتج')
                            ->relationship()
                            ->schema([
                                FileUpload::make('path')
                                    ->label('الصورة')
                                    ->image()
                                    ->imageEditor()
                                    ->directory('products')
                                    ->disk('public')
                                    ->required()
                                    ->columnSpan(2),

                                TextInput::make('alt')
                                    ->label('النص البديل')
                                    ->helperText('وصف الصورة لمحركات البحث وقارئات الشاشة.'),

                                Toggle::make('is_primary')
                                    ->label('الصورة الرئيسية')
                                    ->inline(false),
                            ])
                            ->columns(2)
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['alt'] ?? null)
                            ->addActionLabel('إضافة صورة')
                            ->defaultItems(0),
                    ]),

                Section::make('يتغيّر هذا المنتج حسب')
                    ->description('اختر الخصائص أولاً، ثم وَلِّد المتغيّرات من تبويب «المتغيّرات» بعد الحفظ.')
                    ->schema([
                        Select::make('attributes')
                            ->label('الخصائص')
                            ->relationship('attributes', 'name')
                            ->multiple()
                            ->preload()
                            ->helperText(fn (Get $get): string => blank($get('attributes'))
                                ? 'بدون خصائص، سيكون للمنتج متغيّر واحد فقط.'
                                : 'عدد المتغيّرات الممكنة = حاصل ضرب عدد القيم المختارة من كل خصيصة.'),
                    ]),
            ]);
    }
}
