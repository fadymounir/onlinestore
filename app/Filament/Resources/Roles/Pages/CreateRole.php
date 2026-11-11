<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\AdminPermissions;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /** @var array<string, array<int, string>|null> */
    private array $abilities = [];

    /**
     * `abilities` is the form's own shape — a tick list per resource — and
     * not a column on the role, so it is lifted out before the insert and
     * turned back into permission names once the role exists.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->abilities = $data['abilities'] ?? [];

        unset($data['abilities']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncPermissions(AdminPermissions::flatten($this->abilities));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
