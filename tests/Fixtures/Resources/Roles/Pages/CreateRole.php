<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\Pages;

use BezhanSalleh\FilamentShield\Resources\Roles\Pages\CreateRole as ShieldCreateRole;
use Syriable\Filament\Plugins\PermissionPolicies\Filament\Concerns\ScopesPermissionsOnCreate;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\RoleResource;

final class CreateRole extends ShieldCreateRole
{
    use ScopesPermissionsOnCreate;

    protected static string $resource = RoleResource::class;
}
