<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Providers;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Filament\PanelProvider;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Pages\Reports;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\OrderResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\RoleResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\ServiceResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\UserResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Widgets\SalesChart;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([
                UserResource::class,
                RoleResource::class,
                ServiceResource::class,
                OrderResource::class,
            ])
            ->pages([Reports::class])
            ->widgets([SalesChart::class])
            ->plugins([
                FilamentShieldPlugin::make(),
            ]);
    }
}
