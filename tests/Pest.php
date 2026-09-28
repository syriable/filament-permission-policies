<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;
use Syriable\Filament\Plugins\PermissionPolicies\PermissionManager;
use Syriable\Filament\Plugins\PermissionPolicies\Sources\InMemoryPermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Order;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Service;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\User;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Pages\Reports;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\OrderResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\RoleResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\ServiceResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\UserResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Widgets\SalesChart;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

const RESOURCE_ACTIONS = ['viewAny', 'view', 'create', 'update', 'delete', 'forceDelete', 'reorder'];

/**
 * @param  class-string  $model
 * @param  list<string>  $actions
 */
function resourceGroup(string $resource, string $model, array $actions = RESOURCE_ACTIONS): PermissionGroup
{
    $subject = class_basename($model);

    return new PermissionGroup(
        key: $resource,
        kind: GroupKind::Resource,
        label: $subject,
        model: $model,
        permissions: array_map(
            static fn (string $action): PermissionDefinition => new PermissionDefinition(ucfirst($action).':'.$subject, $action, ucfirst($action)),
            $actions,
        ),
    );
}

/**
 * The universe from the package brief: four resources, one page, one
 * widget and one custom permission.
 */
function exampleCatalog(): PermissionCatalog
{
    return new PermissionCatalog([
        resourceGroup(UserResource::class, User::class),
        resourceGroup(RoleResource::class, Role::class, ['viewAny', 'view', 'create', 'update', 'delete']),
        resourceGroup(ServiceResource::class, Service::class),
        resourceGroup(OrderResource::class, Order::class, ['viewAny', 'view', 'create', 'update', 'delete']),
        new PermissionGroup(Reports::class, GroupKind::Page, 'Reports', permissions: [new PermissionDefinition('View:Reports', 'view', 'Reports')]),
        new PermissionGroup(SalesChart::class, GroupKind::Widget, 'Sales chart', permissions: [new PermissionDefinition('View:SalesChart', 'view', 'Sales chart')]),
        new PermissionGroup('custom', GroupKind::Custom, 'Custom', permissions: [new PermissionDefinition('Export:Reports', null, 'Export reports')]),
    ]);
}

function groupOf(PermissionCatalog $catalog, string $key): PermissionGroup
{
    return $catalog->group($key) ?? throw new RuntimeException("The catalog has no [{$key}] group.");
}

/**
 * Replaces Shield with a fixed universe and returns a fresh manager.
 */
function useCatalog(?PermissionCatalog $catalog = null): PermissionManager
{
    app()->instance(PermissionSource::class, new InMemoryPermissionSource($catalog ?? exampleCatalog()));
    app()->forgetInstance(PermissionManager::class);
    PermissionPolicies::clearResolvedInstance(PermissionManager::class);

    return app(PermissionManager::class);
}
