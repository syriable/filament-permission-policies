<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\Decision;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\PermissionManager;
use Syriable\Filament\Plugins\PermissionPolicies\Rules\Target;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Order;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Service;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\User;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Pages\Reports;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\OrderResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\RoleResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\ServiceResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\UserResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Widgets\SalesChart;

beforeEach(function (): void {
    $this->manager = useCatalog();

    // The "user" context from the package brief.
    $this->manager->context('user')
        ->hideResources([RoleResource::class])
        ->hideModels([User::class])
        ->hideActions(['forceDelete', 'reorder']);
});

describe('admin context', function (): void {
    it('presents the complete universe', function (): void {
        $catalog = $this->manager->forContext('admin');

        expect($catalog)->toEqual($this->manager->universe())
            ->and($catalog->groupKeys(GroupKind::Resource))->toBe([UserResource::class, RoleResource::class, ServiceResource::class, OrderResource::class])
            ->and($catalog->permissionCount())->toBe(27);
    });

    it('keeps every action of every resource', function (): void {
        $catalog = $this->manager->forContext('admin');

        expect($catalog->group(ServiceResource::class)?->keys())
            ->toBe(['ViewAny:Service', 'View:Service', 'Create:Service', 'Update:Service', 'Delete:Service', 'ForceDelete:Service', 'Reorder:Service']);
    });
});

describe('user context', function (): void {
    it('drops restricted resources entirely', function (): void {
        $catalog = $this->manager->forContext('user');

        expect($catalog->groupKeys(GroupKind::Resource))->toBe([ServiceResource::class, OrderResource::class])
            ->and($catalog->models())->toBe([Service::class, Order::class]);
    });

    it('drops restricted permissions entirely', function (): void {
        $catalog = $this->manager->forContext('user');

        expect($catalog->group(ServiceResource::class)?->keys())
            ->toBe(['ViewAny:Service', 'View:Service', 'Create:Service', 'Update:Service', 'Delete:Service'])
            ->and($catalog->contains('ForceDelete:Service'))->toBeFalse()
            ->and($catalog->contains('Reorder:Service'))->toBeFalse();
    });

    it('derives every count from the filtered structure', function (): void {
        $catalog = $this->manager->forContext('user');

        expect($catalog->groupCount(GroupKind::Resource))->toBe(2)
            ->and($catalog->permissionCount(GroupKind::Resource))->toBe(10)
            ->and($catalog->permissionCount())->toBe(13)
            ->and($catalog->selectedCount(['View:Service', 'ForceDelete:Service', 'Delete:Role']))->toBe(1);
    });

    it('leaves pages, widgets and custom permissions to their own rules', function (): void {
        $catalog = $this->manager->forContext('user');

        expect($catalog->groupKeys(GroupKind::Page))->toBe([Reports::class])
            ->and($catalog->groupKeys(GroupKind::Widget))->toBe([SalesChart::class])
            ->and($catalog->keys(GroupKind::Custom))->toBe(['Export:Reports']);
    });

    it('does not change the admin context', function (): void {
        expect($this->manager->forContext('admin')->permissionCount())->toBe(27);
    });
});

describe('permission rules', function (): void {
    it('hides one permission', function (): void {
        $this->manager->context('seller')->hidePermissions(['Delete:Order']);

        expect($this->manager->forContext('seller')->group(OrderResource::class)?->keys())
            ->toBe(['ViewAny:Order', 'View:Order', 'Create:Order', 'Update:Order']);
    });

    it('hides several permissions', function (): void {
        $this->manager->context('seller')->hidePermissions(['Delete:Order', 'Create:Order', 'Reorder:Service']);

        $catalog = $this->manager->forContext('seller');

        expect($catalog->permissionCount())->toBe(24)
            ->and($catalog->intersect(['Delete:Order', 'Create:Order', 'Reorder:Service']))->toBe([]);
    });

    it('allows only specific permissions', function (): void {
        $this->manager->context('auditor')->onlyPermissions(['ViewAny:Order', 'View:Order', 'View:Reports']);

        expect($this->manager->forContext('auditor')->keys())->toBe(['ViewAny:Order', 'View:Order', 'View:Reports']);
    });

    it('allows only specific actions on resources', function (): void {
        $this->manager->context('viewer')->onlyActions(['viewAny', 'view']);

        $catalog = $this->manager->forContext('viewer');

        expect($catalog->permissionCount(GroupKind::Resource))->toBe(8)
            ->and($catalog->permissionCount(GroupKind::Page))->toBe(1);
    });

    it('hides a single action on a single resource', function (): void {
        $this->manager->context('seller')->deny(Target::make()->groups([ServiceResource::class])->actions(['delete']));

        $catalog = $this->manager->forContext('seller');

        expect($catalog->contains('Delete:Service'))->toBeFalse()
            ->and($catalog->contains('Delete:Order'))->toBeTrue();
    });

    it('hides whole kinds', function (): void {
        $this->manager->context('seller')->hideKinds([GroupKind::Widget, GroupKind::Custom]);

        $catalog = $this->manager->forContext('seller');

        expect($catalog->groups(GroupKind::Widget))->toBe([])
            ->and($catalog->groups(GroupKind::Custom))->toBe([])
            ->and($catalog->groupCount(GroupKind::Resource))->toBe(4);
    });

    it('hides pages and widgets by class', function (): void {
        $this->manager->context('seller')->hidePages([Reports::class])->hideWidgets([SalesChart::class]);

        $catalog = $this->manager->forContext('seller');

        expect($catalog->groups(GroupKind::Page))->toBe([])
            ->and($catalog->groups(GroupKind::Widget))->toBe([]);
    });

    it('does not apply action rules to pages', function (): void {
        $this->manager->context('seller')->hideActions(['view']);

        expect($this->manager->forContext('seller')->contains('View:Reports'))->toBeTrue();
    });
});

describe('resource and model rules', function (): void {
    it('hides a resource by class', function (): void {
        $this->manager->context('seller')->hideResources([OrderResource::class]);

        expect($this->manager->forContext('seller')->hasGroup(OrderResource::class))->toBeFalse();
    });

    it('hides every resource that manages a model', function (): void {
        $manager = useCatalog(exampleCatalog()->merge(new Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog([
            resourceGroup('App\Filament\Resources\FeaturedServiceResource', Service::class, ['view']),
        ])));

        $manager->context('seller')->hideModels([Service::class]);

        $catalog = $manager->forContext('seller');

        expect($catalog->hasGroup(ServiceResource::class))->toBeFalse()
            ->and($catalog->hasGroup('App\Filament\Resources\FeaturedServiceResource'))->toBeFalse()
            ->and($catalog->models())->toBe([User::class, Role::class, Order::class]);
    });

    it('shows only the listed resources and leaves other kinds alone', function (): void {
        $this->manager->context('seller')->onlyResources([ServiceResource::class]);

        $catalog = $this->manager->forContext('seller');

        expect($catalog->groupKeys(GroupKind::Resource))->toBe([ServiceResource::class])
            ->and($catalog->groupCount(GroupKind::Page))->toBe(1);
    });

    it('shows only the resources managing the listed models', function (): void {
        $this->manager->context('seller')->onlyModels([Order::class]);

        expect($this->manager->forContext('seller')->groupKeys(GroupKind::Resource))->toBe([OrderResource::class]);
    });
});

describe('extensibility', function (): void {
    it('adds a context by declaring its rules, without touching the engine', function (): void {
        $this->manager->context('moderator')
            ->onlyResources([ServiceResource::class, OrderResource::class])
            ->hideActions(['delete']);

        $catalog = $this->manager->forContext('moderator');

        expect($catalog->groupKeys(GroupKind::Resource))->toBe([ServiceResource::class, OrderResource::class])
            ->and($catalog->contains('Delete:Service'))->toBeFalse()
            ->and($catalog->contains('ForceDelete:Service'))->toBeTrue()
            ->and($this->manager->registry()->contexts())->toBe(['user', 'moderator']);
    });

    it('applies only global rules to a context nobody declared', function (): void {
        $this->manager->global()->hideKinds([GroupKind::Custom]);

        $catalog = $this->manager->forContext('unknown');

        expect($catalog->permissionCount())->toBe(26)
            ->and($this->manager->registry()->hasContext('unknown'))->toBeFalse();
    });

    it('accepts custom rule objects and closures', function (): void {
        $this->manager->context('seller')
            ->rule(fn (PermissionDefinition $permission): Decision => str_starts_with($permission->key, 'Create:') ? Decision::Deny : Decision::Abstain)
            ->rule(new class implements Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionRule
            {
                public function decide(PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): Decision
                {
                    return $group->kind === GroupKind::Widget ? Decision::Deny : Decision::Abstain;
                }
            });

        $catalog = $this->manager->forContext('seller');

        expect($catalog->intersect(['Create:Order', 'Create:Service', 'View:SalesChart']))->toBe([]);
    });

    it('decides on context attributes, such as the role being edited', function (): void {
        $this->manager->context('seller')->hideWhen(
            fn (PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): bool => $context->attribute('tier') !== 'pro' && $group->key === OrderResource::class,
        );

        expect($this->manager->forContext(PermissionContext::make('seller', ['tier' => 'basic']))->hasGroup(OrderResource::class))->toBeFalse()
            ->and($this->manager->forContext(PermissionContext::make('seller', ['tier' => 'pro']))->hasGroup(OrderResource::class))->toBeTrue();
    });

    it('resolves the context from a role guard by default', function (): void {
        $context = $this->manager->resolveContext(['guard_name' => 'user']);

        expect($context->name)->toBe('user')
            ->and($this->manager->forRole(null, ['guard_name' => 'user'])->groupCount(GroupKind::Resource))->toBe(2);
    });

    it('lets the application swap the context resolver', function (): void {
        app()->bind(Syriable\Filament\Plugins\PermissionPolicies\Contracts\ContextResolver::class, fn (): Syriable\Filament\Plugins\PermissionPolicies\Contracts\ContextResolver => new class implements Syriable\Filament\Plugins\PermissionPolicies\Contracts\ContextResolver
        {
            public function resolve(array $state = [], ?Illuminate\Database\Eloquent\Model $role = null): PermissionContext
            {
                return PermissionContext::make(($state['type'] ?? null) === 'member' ? 'user' : 'admin');
            }
        });

        $manager = useCatalog();
        $manager->context('user')->hideResources([RoleResource::class]);

        expect($manager->forRole(null, ['type' => 'member'])->hasGroup(RoleResource::class))->toBeFalse()
            ->and($manager->forRole(null, ['type' => 'staff'])->hasGroup(RoleResource::class))->toBeTrue();
    });
});

it('is shared through the container for the whole request', function (): void {
    expect(app(PermissionManager::class))->toBe(app(PermissionManager::class));
});
