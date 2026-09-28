<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\Pages;

use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles as ShieldListRoles;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\RoleResource;

final class ListRoles extends ShieldListRoles
{
    protected static string $resource = RoleResource::class;
}
