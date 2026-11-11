<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\AdminPermissions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                            ->helperText('مثال: مدير المتجر'),

                        // Every role created here belongs to the admin panel.
                        Hidden::make('guard_name')
                            ->default('admin'),
                    ]),

                Section::make('الصلاحيات')
                    ->description('لكل قسم أربع صلاحيات: عرض، إضافة، تعديل، حذف. '
                        .'دور "super_admin" يملك كل شيء تلقائياً دون تحديد أي صلاحية هنا.')
                    ->schema(self::resourceGroups())
                    ->columns(2),
            ]);
    }

    /**
     * One box of four checkboxes per resource.
     *
     * The permissions are a flat list of `resource.ability` names in the
     * database, and shown that way they are fifty-odd identical-looking
     * English strings. Grouped by resource under an Arabic heading they read
     * as the question actually being asked: what may this role do to the
     * products, to the orders, to the settings.
     *
     * The state lives at `abilities.<resource>` rather than on the
     * `permissions` relationship — a relationship can be bound to one field,
     * not thirteen — and CreateRole and EditRole put it back together.
     *
     * @return array<int, Component>
     */
    private static function resourceGroups(): array
    {
        $groups = [];

        foreach (AdminPermissions::RESOURCES as $resource => $heading) {
            $groups[] = Fieldset::make($heading)
                ->schema([
                    CheckboxList::make("abilities.{$resource}")
                        ->hiddenLabel()
                        ->options(AdminPermissions::ABILITIES)
                        ->bulkToggleable()
                        ->columns(2),
                ])
                ->columns(1);
        }

        return $groups;
    }
}
