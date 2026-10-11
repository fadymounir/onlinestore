<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الدور')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم الدور')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('مثال: مدير_المتجر'),

                        // Every role created here belongs to the admin panel.
                        Hidden::make('guard_name')
                            ->default('admin'),
                    ]),

                Section::make('الصلاحيات')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('الصلاحيات')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('guard_name', 'admin'),
                            )
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->noSearchResultsMessage('لا توجد صلاحيات مطابقة.')
                            ->helperText('دور "super_admin" يمتلك كل الصلاحيات تلقائياً دون تحديدها.'),
                    ]),
            ]);
    }
}
