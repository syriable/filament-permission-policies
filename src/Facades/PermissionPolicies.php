<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Facades;

use Illuminate\Support\Facades\Facade;
use Syriable\Filament\Plugins\PermissionPolicies\PermissionManager;

/**
 * @method static \Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog universe()
 * @method static \Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog forContext(\Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext|string $context)
 * @method static \Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog forRole(?\Illuminate\Database\Eloquent\Model $role, array<string, mixed> $state = [])
 * @method static \Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext resolveContext(array<string, mixed> $state = [], ?\Illuminate\Database\Eloquent\Model $role = null)
 * @method static \Syriable\Filament\Plugins\PermissionPolicies\PermissionPolicy global()
 * @method static \Syriable\Filament\Plugins\PermissionPolicies\PermissionPolicy context(string $name)
 * @method static \Syriable\Filament\Plugins\PermissionPolicies\RoleGuards guards()
 * @method static \Syriable\Filament\Plugins\PermissionPolicies\PolicyRegistry registry()
 *
 * @see PermissionManager
 */
final class PermissionPolicies extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PermissionManager::class;
    }
}
