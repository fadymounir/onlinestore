<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات التصنيف')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم التصنيف')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (filled($state) && blank($get('slug'))) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('المعرّف في الرابط')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9-]+$/']),

                        Select::make('parent_id')
                            ->label('التصنيف الأب')
                            ->placeholder('تصنيف رئيسي (بلا أب)')
                            ->searchable()
                            ->options(fn (?Model $record): array => CategoryNodeForm::parentOptions(
                                // A category may not be its own parent, nor sit
                                // under one of its own descendants.
                                exclude: $record instanceof Category ? $record->subtreeIds() : [],
                            ))
                            ->helperText('اتركه فارغاً ليكون تصنيفاً رئيسياً في القائمة. الترتيب بين الأشقاء يُضبط من صفحة الشجرة.'),

                        Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('الصورة والحالة')
                    ->schema([
                        FileUpload::make('image')
                            ->label('صورة التصنيف')
                            ->image()
                            ->imageEditor()
                            ->directory('categories')
                            ->disk('public'),

                        Toggle::make('is_active')
                            ->label('مُفعّل')
                            ->default(true)
                            ->helperText('إيقافه يخفيه من الموقع مع كل منتجاته وكل ما تحته.'),
                    ])
                    ->columns(2),
            ]);
    }
}
