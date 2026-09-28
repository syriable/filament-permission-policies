<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;
use Syriable\Filament\Plugins\PermissionPolicies\Shield\ShieldPermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Order;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Service;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Pages\Reports;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\OrderResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\RoleResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\ServiceResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\UserResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Widgets\SalesChart;

it('uses Shield as the default source', function (): void {
    expect(app(PermissionSource::class))->toBeInstanceOf(ShieldPermissionSource::class);
});

it('contains exactly the permissions Shield generates', function (): void {
    expect(PermissionPolicies::universe()->keys())
        ->toEqualCanonicalizing(FilamentShield::getEntitiesPermissions());
});

it('turns every discovered resource into a group with its model and actions', function (): void {
    $catalog = PermissionPolicies::universe();

    expect($catalog->groupKeys(GroupKind::Resource))
        ->toEqualCanonicalizing([UserResource::class, RoleResource::class, ServiceResource::class, OrderResource::class]);

    $service = $catalog->group(ServiceResource::class);

    expect($service)->not->toBeNull()
        ->and($service?->model)->toBe(Service::class)
        ->and($service?->label)->toBe('Service')
        ->and($service?->permissions[0]->key)->toBe('ViewAny:Service')
        ->and($service?->permissions[0]->action)->toBe('viewAny')
        ->and(array_map(fn ($permission): ?string => $permission->action, $service->permissions ?? []))
        ->toBe(['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'restore', 'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder']);
});

it('follows the resource methods Shield is configured with', function (): void {
    config()->set('filament-shield.policies.merge', false);
    config()->set('filament-shield.resources.manage', [OrderResource::class => ['viewAny', 'view']]);

    expect(PermissionPolicies::universe()->group(OrderResource::class)?->keys())->toBe(['ViewAny:Order', 'View:Order']);
});

it('leaves out resources Shield excludes', function (): void {
    config()->set('filament-shield.resources.exclude', [OrderResource::class]);

    expect(PermissionPolicies::universe()->hasGroup(OrderResource::class))->toBeFalse()
        ->and(PermissionPolicies::universe()->models())->not->toContain(Order::class);
});

it('reads pages and widgets with their prefix as action', function (): void {
    $catalog = PermissionPolicies::universe();

    expect($catalog->group(Reports::class)?->keys())->toBe(['View:Reports'])
        ->and($catalog->group(Reports::class)?->permissions[0]->action)->toBe('view')
        ->and($catalog->group(SalesChart::class)?->keys())->toBe(['View:SalesChart']);
});

it('reads custom permissions into one group without an action', function (): void {
    config()->set('filament-shield.custom_permissions', ['export:reports' => 'Export reports']);

    $group = PermissionPolicies::universe()->group(ShieldPermissionSource::CUSTOM_GROUP);

    expect($group?->kind)->toBe(GroupKind::Custom)
        ->and($group?->keys())->toBe(['Export:Reports'])
        ->and($group?->permissions[0]->action)->toBeNull();
});

it('follows a custom permission key format', function (): void {
    config()->set('filament-shield.permissions.case', 'snake');
    config()->set('filament-shield.permissions.separator', '.');

    expect(PermissionPolicies::universe()->group(ServiceResource::class)?->keys())->toContain('force_delete.service');

    PermissionPolicies::context('user')->hideActions(['forceDelete']);

    expect(PermissionPolicies::forContext('user')->contains('force_delete.service'))->toBeFalse();
});
