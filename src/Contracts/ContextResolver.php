<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Contracts;

use Illuminate\Database\Eloquent\Model;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;

/**
 * Works out which context a role's permissions are managed in.
 */
interface ContextResolver
{
    /**
     * @param  array<string, mixed>  $state  The role's current, possibly unsaved, attributes (such as "guard_name").
     * @param  Model|null  $role  The stored role, or null while one is being created.
     */
    public function resolve(array $state = [], ?Model $role = null): PermissionContext;
}
