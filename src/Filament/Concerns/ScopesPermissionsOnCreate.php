<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Filament\Concerns;

use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;

/**
 * Use on a page extending Shield's CreateRole.
 *
 * Shield collects the submitted permissions into $permissions and gives them
 * to the new role. This drops every key the role's context does not present
 * first, so a crafted request cannot grant a hidden permission.
 */
trait ScopesPermissionsOnCreate
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = parent::mutateFormDataBeforeCreate($data);

        $this->permissions = collect(PermissionPolicies::forRole(null, $data)->intersect($this->permissions));

        return $data;
    }
}
