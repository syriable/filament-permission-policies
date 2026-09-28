<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\Pages;

use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole as ShieldEditRole;
use Syriable\Filament\Plugins\PermissionPolicies\Filament\Concerns\ScopesPermissionsOnSave;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\RoleResource;

final class EditRole extends ShieldEditRole
{
    use ScopesPermissionsOnSave;

    protected static string $resource = RoleResource::class;
}
