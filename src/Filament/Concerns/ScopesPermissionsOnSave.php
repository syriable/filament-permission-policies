<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Filament\Concerns;

use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;

/**
 * Use on a page extending Shield's EditRole.
 *
 * Shield syncs the role to the submitted permissions. This drops every key
 * the role's context does not present first: a crafted request cannot grant
 * a hidden permission, and a role moved to a narrower context (by changing
 * its guard, for example) loses the permissions that context does not manage.
 */
trait ScopesPermissionsOnSave
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);

        $this->permissions = collect(PermissionPolicies::forRole($this->getRecord(), $data)->intersect($this->permissions));

        return $data;
    }
}
