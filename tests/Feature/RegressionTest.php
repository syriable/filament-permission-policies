<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\OrderResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\RoleResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\ServiceResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\UserResource;

/*
 * Behaviour carried over from the prototype this package replaces: member
 * roles (web guard) never see the role resource, nor the restore, force
 * delete, replicate and reorder abilities, while administrator roles see the
 * whole universe Shield generates.
 */

const MEMBER_HIDDEN_ACTIONS = ['restore', 'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder'];

beforeEach(function (): void {
    PermissionPolicies::context('web')
        ->hideResources([RoleResource::class])
        ->hideActions(MEMBER_HIDDEN_ACTIONS);
});

it('hides the role resource from member roles', function (): void {
    expect(PermissionPolicies::forContext('web')->groupKeys(GroupKind::Resource))
        ->toEqualCanonicalizing([UserResource::class, ServiceResource::class, OrderResource::class]);
});

it('hides the same abilities on every remaining resource', function (): void {
    $catalog = PermissionPolicies::forContext('web');

    foreach ($catalog->groups(GroupKind::Resource) as $group) {
        expect(array_map(fn (Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition $permission): ?string => $permission->action, $group->permissions))
            ->toBe(['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny']);
    }
});

it('counts what member roles are shown, not what Shield generated', function (): void {
    $catalog = PermissionPolicies::forContext('web');

    expect($catalog->permissionCount(GroupKind::Resource))->toBe(3 * 6)
        ->and($catalog->permissionCount())->toBe(3 * 6 + 2)
        ->and(PermissionPolicies::universe()->permissionCount())->toBe(4 * 12 + 2);
});

it('shows administrator roles the full universe', function (): void {
    expect(PermissionPolicies::forContext('admin')->keys())
        ->toBe(PermissionPolicies::universe()->keys());
});

it('does not hide page or widget permissions from member roles', function (): void {
    $catalog = PermissionPolicies::forContext('web');

    expect($catalog->keys(GroupKind::Page))->toBe(['View:Reports'])
        ->and($catalog->keys(GroupKind::Widget))->toBe(['View:SalesChart']);
});
