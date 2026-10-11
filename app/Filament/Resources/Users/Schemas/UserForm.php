<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات العميل')
                    ->schema([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('phone')
                            ->label('رقم الهاتف')
                            ->tel()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->rules(['nullable', 'regex:/^01[0-2,5]\d{8}$/'])
                            ->helperText('رقم مصري من 11 رقماً، مثل 01012345678.'),

                        Toggle::make('is_active')
                            ->label('حساب نشط')
                            ->default(true)
                            ->helperText('إيقافه يمنع العميل من الدخول وإتمام الطلبات.'),
                    ])
                    ->columns(2),

                Section::make('كلمة المرور')
                    ->description('اتركها فارغة عند التعديل للإبقاء على كلمة المرور الحالية.')
                    ->schema([
                        TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),
            ]);
    }
}
