<?php

namespace App\Filament\Resources\Admins\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات المشرف')
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

                        Toggle::make('is_active')
                            ->label('نشط')
                            ->helperText('المشرف غير النشط لا يستطيع الدخول إلى لوحة التحكم.')
                            ->default(true),
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
                            // Only overwrite the stored hash when a value was typed.
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->confirmed(),

                        TextInput::make('password_confirmation')
                            ->label('تأكيد كلمة المرور')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->requiredWith('password')
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Section::make('الأدوار')
                    ->schema([
                        Select::make('roles')
                            ->label('الأدوار')
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('guard_name', 'admin'),
                            )
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }
}
