<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Order;

final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function getPages(): array
    {
        return [];
    }
}
