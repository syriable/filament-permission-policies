<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Service;

final class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    public static function getPages(): array
    {
        return [];
    }
}
