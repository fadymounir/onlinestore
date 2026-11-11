<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\AdminPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
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
                    ])
                    ->columnSpanFull(),

                Section::make('الصلاحيات')
                    ->headerActions(self::bulkActions())
                    ->schema(self::resourceGroups())
                    // Four small boxes to a row on a wide screen, folding to
                    // two and then one as it narrows. Thirteen resources read
                    // as a grid to scan, not a column to scroll.
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Grant or revoke everything at once.
     *
     * Each box carries its own four switches; these two are for the common
     * starting points — open a role up and pare it back, or start from
     * nothing and add.
     *
     * @return array<int, Action>
     */
    private static function bulkActions(): array
    {
        return [
            Action::make('grantAll')
                ->label('تحديد كل الصلاحيات')
                ->icon('heroicon-m-check-circle')
                ->link()
                ->action(fn (Set $set) => $set('abilities', AdminPermissions::matrix(granted: true))),

            Action::make('revokeAll')
                ->label('إلغاء تحديد الكل')
                ->icon('heroicon-m-x-circle')
                ->color('gray')
                ->link()
                ->action(fn (Set $set) => $set('abilities', AdminPermissions::matrix())),
        ];
    }

    /**
     * One box of four switches per resource.
     *
     * The permissions are a flat list of `resource.ability` names in the
     * database, and shown that way they are fifty-odd identical-looking
     * English strings. Gathered into a small box per resource under an Arabic
     * heading, they read as the question actually being asked: what may this
     * role do to the products, to the orders, to the settings.
     *
     * The state lives at `abilities.<resource>.<ability>` rather than on the
     * `permissions` relationship — a relationship can be bound to one field,
     * not fifty-two — and CreateRole and EditRole put it back together.
     *
     * @return array<int, Component>
     */
    private static function resourceGroups(): array
    {
        $groups = [];

        foreach (AdminPermissions::RESOURCES as $resource => $heading) {
            $switches = [];

            foreach (AdminPermissions::ABILITIES as $ability => $label) {
                // Label beside the switch, not above it: four stacked pairs
                // made a tall box out of four short words.
                $switches[] = Toggle::make("abilities.{$resource}.{$ability}")
                    ->label($label)
                    ->inline()
                    ->default(false);
            }

            // Two by two inside a quarter-width box: four across would
            // leave each switch about a sixteenth of the page.
            $groups[] = Fieldset::make($heading)
                ->schema($switches)
                ->columns(2);
        }

        return $groups;
    }
}
