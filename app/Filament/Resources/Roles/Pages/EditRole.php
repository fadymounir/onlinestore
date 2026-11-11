<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\AdminPermissions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** @var array<string, array<int, string>|null> */
    private array $abilities = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * The role's permissions arrive as a flat list of names; the form wants
     * them grouped by resource. See RoleForm::resourceGroups().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['abilities'] = AdminPermissions::group(
            $this->record->permissions->pluck('name'),
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->abilities = $data['abilities'] ?? [];

        unset($data['abilities']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncPermissions(AdminPermissions::flatten($this->abilities));

        // Spatie caches the permission map; an edited role must take effect
        // on the next request, not after the cache happens to expire.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
