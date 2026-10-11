<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الماركة')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم الماركة')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state) ?: Str::random(8));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('المعرّف في الرابط')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9-]+$/'])
                            ->helperText('أسماء الماركات العربية لا تُولّد معرّفاً تلقائياً — اكتبه بالإنجليزية.'),

                        FileUpload::make('logo')
                            ->label('الشعار')
                            ->image()
                            ->imageEditor()
                            ->directory('brands')
                            ->disk('public'),

                        Toggle::make('is_active')
                            ->label('مُفعّل')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
