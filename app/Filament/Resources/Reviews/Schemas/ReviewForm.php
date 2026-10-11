<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Review;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('التقييم')
                    ->schema([
                        Select::make('product_id')
                            ->label('المنتج')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabledOn('edit'),

                        Select::make('user_id')
                            ->label('العميل')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabledOn('edit'),

                        Select::make('rating')
                            ->label('التقييم')
                            ->options([
                                5 => '★★★★★ ممتاز',
                                4 => '★★★★ جيد جداً',
                                3 => '★★★ مقبول',
                                2 => '★★ ضعيف',
                                1 => '★ سيئ',
                            ])
                            ->required(),

                        Placeholder::make('verified')
                            ->label('شراء موثّق')
                            ->content(fn (?Review $record): string => $record?->isVerifiedPurchase()
                                ? 'نعم — العميل اشترى هذا المنتج فعلاً'
                                : 'لا — غير مرتبط بطلب'),

                        TextInput::make('title')
                            ->label('العنوان')
                            ->maxLength(255),

                        Textarea::make('comment')
                            ->label('نص التقييم')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('يمكنك تحرير النص لإزالة أي كلمات غير لائقة قبل النشر.'),
                    ])
                    ->columns(2),

                Section::make('النشر')
                    ->schema([
                        Toggle::make('is_approved')
                            ->label('منشور')
                            ->helperText('التقييمات غير المنشورة لا تظهر في صفحة المنتج.'),
                    ]),
            ]);
    }
}
