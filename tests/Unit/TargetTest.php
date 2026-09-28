<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\Rules\Target;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Order;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Service;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\OrderResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\ServiceResource;

beforeEach(function (): void {
    $this->service = resourceGroup(ServiceResource::class, Service::class);
    $this->order = resourceGroup(OrderResource::class, Order::class);
});

function permissionOf(PermissionGroup $group, string $action): PermissionDefinition
{
    foreach ($group->permissions as $permission) {
        if ($permission->action === $action) {
            return $permission;
        }
    }

    throw new RuntimeException("No [{$action}] permission.");
}

it('matches everything when nothing is set', function (): void {
    expect(Target::make()->matches(permissionOf($this->order, 'view'), $this->order))->toBeTrue();
});

it('requires every set dimension to match', function (): void {
    $target = Target::make()->groups([ServiceResource::class])->actions(['delete']);

    expect($target->matches(permissionOf($this->service, 'delete'), $this->service))->toBeTrue()
        ->and($target->matches(permissionOf($this->service, 'view'), $this->service))->toBeFalse()
        ->and($target->matches(permissionOf($this->order, 'delete'), $this->order))->toBeFalse();
});

it('matches any value within one dimension', function (): void {
    $target = Target::make()->models([Service::class, Order::class]);

    expect($target->matches(permissionOf($this->service, 'view'), $this->service))->toBeTrue()
        ->and($target->matches(permissionOf($this->order, 'view'), $this->order))->toBeTrue();
});

it('accepts actions in any case', function (string $action): void {
    expect(Target::make()->actions([$action])->matches(permissionOf($this->order, 'forceDelete'), $this->order))->toBeTrue();
})->with(['forceDelete', 'ForceDelete', 'force_delete', 'force-delete']);

it('never matches a model or action a permission does not have', function (): void {
    $custom = new PermissionGroup('custom', GroupKind::Custom, 'Custom', permissions: [new PermissionDefinition('Export:Reports', null, 'Export')]);

    expect(Target::make()->models([Service::class])->matches($custom->permissions[0], $custom))->toBeFalse()
        ->and(Target::make()->actions(['export'])->matches($custom->permissions[0], $custom))->toBeFalse();
});

it('is immutable', function (): void {
    $base = Target::make();
    $narrowed = $base->kinds([GroupKind::Page]);

    expect($base->kinds)->toBeNull()
        ->and($narrowed->kinds)->toBe([GroupKind::Page]);
});
