<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\User;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getPages(): array
    {
        return [];
    }
}
