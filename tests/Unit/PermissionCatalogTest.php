<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Order;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Service;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\OrderResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\ServiceResource;

it('counts groups and permissions per kind', function (): void {
    $catalog = exampleCatalog();

    expect($catalog->groupCount())->toBe(7)
        ->and($catalog->groupCount(GroupKind::Resource))->toBe(4)
        ->and($catalog->permissionCount(GroupKind::Resource))->toBe(24)
        ->and($catalog->permissionCount(GroupKind::Page))->toBe(1)
        ->and($catalog->permissionCount())->toBe(27);
});

it('never holds an empty group', function (): void {
    $catalog = new PermissionCatalog([
        new PermissionGroup('empty', GroupKind::Custom, 'Empty'),
        resourceGroup(OrderResource::class, Order::class, ['view']),
    ]);

    expect($catalog->groupKeys())->toBe([OrderResource::class]);
});

it('drops groups a filter empties', function (): void {
    $catalog = exampleCatalog()->filter(
        fn (PermissionDefinition $permission, PermissionGroup $group): bool => $group->key !== ServiceResource::class,
    );

    expect($catalog->hasGroup(ServiceResource::class))->toBeFalse()
        ->and($catalog->groupCount(GroupKind::Resource))->toBe(3)
        ->and($catalog->permissionCount(GroupKind::Resource))->toBe(17);
});

it('lists the models behind resources once each', function (): void {
    $catalog = new PermissionCatalog([
        resourceGroup(ServiceResource::class, Service::class),
        resourceGroup('App\Filament\Resources\FeaturedServiceResource', Service::class),
        resourceGroup(OrderResource::class, Order::class),
    ]);

    expect($catalog->models())->toBe([Service::class, Order::class]);
});

it('intersects keys in catalog order and ignores unknown or non-string keys', function (): void {
    $catalog = exampleCatalog();

    expect($catalog->intersect(['Delete:Order', 'Nope:Nope', 42, 'ViewAny:Order']))
        ->toBe(['ViewAny:Order', 'Delete:Order']);
});

it('never counts more selected permissions than it presents', function (): void {
    $catalog = new PermissionCatalog([resourceGroup(OrderResource::class, Order::class, ['view', 'update'])]);

    expect($catalog->selectedCount(['View:Order', 'ForceDelete:Order', 'Update:Order', 'View:Order']))->toBe(2);
});

it('merges catalogs and keeps the first group with a given key', function (): void {
    $first = new PermissionCatalog([resourceGroup(OrderResource::class, Order::class, ['view'])]);
    $second = new PermissionCatalog([
        resourceGroup(OrderResource::class, Order::class, ['delete']),
        resourceGroup(ServiceResource::class, Service::class, ['view']),
    ]);

    $merged = $first->merge($second);

    expect($merged->keys())->toBe(['View:Order', 'View:Service']);
});

it('builds checkbox options keyed by permission', function (): void {
    $group = resourceGroup(OrderResource::class, Order::class, ['view', 'update']);

    expect($group->options())->toBe(['View:Order' => 'View', 'Update:Order' => 'Update'])
        ->and(exampleCatalog()->options(GroupKind::Custom))->toBe(['Export:Reports' => 'Export reports']);
});
