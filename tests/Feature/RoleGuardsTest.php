<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\Exceptions\InvalidPolicyConfiguration;
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;
use Syriable\Filament\Plugins\PermissionPolicies\PermissionManager;
use Syriable\Filament\Plugins\PermissionPolicies\PolicyRegistry;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Service;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\User;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Pages\Reports;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\OrderResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\Pages\CreateRole;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\RoleResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\ServiceResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Widgets\SalesChart;

/**
 * Sets the package config and rebuilds the policies from it, as a fresh
 * request would.
 *
 * @param  array<string, mixed>  $guards
 * @param  array<string, mixed>  $global
 */
function configurePolicies(array $guards, array $global = []): void
{
    config()->set('auth.guards.api', ['driver' => 'session', 'provider' => 'users']);
    config()->set('filament-permission-policies.guards', $guards);
    config()->set('filament-permission-policies.global', $global);

    app()->forgetInstance(PolicyRegistry::class);
    app()->forgetInstance(PermissionManager::class);
    PermissionPolicies::clearResolvedInstance(PermissionManager::class);
}

describe('guards', function (): void {
    it('offers every auth guard when none are configured', function (): void {
        configurePolicies([]);

        expect(PermissionPolicies::guards()->options())->toBe(['web' => 'Web', 'admin' => 'Admin', 'api' => 'Api']);
    });

    it('offers only the configured guards, in their order', function (): void {
        configurePolicies(['admin' => [], 'api' => [], 'web' => []]);

        expect(PermissionPolicies::guards()->names())->toBe(['admin', 'api', 'web'])
            ->and(PermissionPolicies::guards()->has('api'))->toBeTrue()
            ->and(PermissionPolicies::guards()->has('sanctum'))->toBeFalse();
    });

    it('uses plain labels, translation keys and colors', function (): void {
        Lang::addLines(['roles.guards.web' => 'عضو'], 'en');

        configurePolicies([
            'admin' => ['label' => 'Administrator', 'color' => 'primary'],
            'web' => ['label' => 'roles.guards.web'],
            'api' => [],
        ]);

        $guards = PermissionPolicies::guards();

        expect($guards->options())->toBe(['admin' => 'Administrator', 'web' => 'عضو', 'api' => 'Api'])
            ->and($guards->color('admin'))->toBe('primary')
            ->and($guards->color('api'))->toBe('gray');
    });
});

describe('rules from the config file', function (): void {
    it('hides resources and actions for one guard and leaves the others whole', function (): void {
        configurePolicies([
            'admin' => [],
            'web' => ['hide' => [
                'resources' => [RoleResource::class],
                'actions' => ['restore', 'restoreAny', 'forceDelete', 'forceDeleteAny', 'replicate', 'reorder'],
            ]],
        ]);

        $web = PermissionPolicies::forContext('web');

        expect($web->hasGroup(RoleResource::class))->toBeFalse()
            ->and($web->permissionCount(GroupKind::Resource))->toBe(3 * 6)
            ->and(PermissionPolicies::forContext('admin')->keys())->toBe(PermissionPolicies::universe()->keys());
    });

    it('hides models, exact permissions, pages, widgets and whole kinds', function (): void {
        configurePolicies(['web' => ['hide' => [
            'models' => [Service::class],
            'permissions' => ['Delete:Order'],
            'pages' => [Reports::class],
            'widgets' => [SalesChart::class],
        ]], 'api' => ['hide' => ['kinds' => ['page', 'widget', 'custom']]]]);

        $web = PermissionPolicies::forContext('web');
        $api = PermissionPolicies::forContext('api');

        expect($web->hasGroup(ServiceResource::class))->toBeFalse()
            ->and($web->contains('Delete:Order'))->toBeFalse()
            ->and($web->groups(GroupKind::Page))->toBe([])
            ->and($web->groups(GroupKind::Widget))->toBe([])
            ->and($api->groups(GroupKind::Page))->toBe([])
            ->and($api->groupCount(GroupKind::Resource))->toBe(4);
    });

    it('applies allow-lists', function (): void {
        configurePolicies(['api' => ['only' => [
            'resources' => [ServiceResource::class, OrderResource::class],
            'actions' => ['viewAny', 'view'],
        ]]]);

        $api = PermissionPolicies::forContext('api');

        expect($api->keys(GroupKind::Resource))->toBe(['ViewAny:Order', 'View:Order', 'ViewAny:Service', 'View:Service']);
    });

    it('applies global rules to every guard and lets a guard allow them back', function (): void {
        configurePolicies(
            ['admin' => ['allow' => ['actions' => ['reorder']]], 'web' => []],
            ['hide' => ['actions' => ['reorder']]],
        );

        expect(PermissionPolicies::forContext('web')->contains('Reorder:Service'))->toBeFalse()
            ->and(PermissionPolicies::forContext('admin')->contains('Reorder:Service'))->toBeTrue();
    });

    it('allows pages and widgets back per guard', function (): void {
        configurePolicies(
            ['admin' => ['allow' => ['pages' => [Reports::class], 'widgets' => [SalesChart::class]]]],
            ['hide' => ['kinds' => ['page', 'widget']]],
        );

        expect(PermissionPolicies::forContext('admin')->keys(GroupKind::Page))->toBe(['View:Reports'])
            ->and(PermissionPolicies::forContext('admin')->keys(GroupKind::Widget))->toBe(['View:SalesChart'])
            ->and(PermissionPolicies::forContext('web')->groups(GroupKind::Page))->toBe([]);
    });

    it('keeps a hidden item hidden inside one guard, whatever it allows', function (): void {
        configurePolicies(['web' => [
            'hide' => ['resources' => [ServiceResource::class]],
            'allow' => ['permissions' => ['View:Service']],
        ]]);

        expect(PermissionPolicies::forContext('web')->contains('View:Service'))->toBeFalse();
    });

    it('ignores empty lists instead of hiding everything', function (): void {
        configurePolicies(['web' => ['only' => ['resources' => []], 'hide' => ['actions' => []]]]);

        expect(PermissionPolicies::forContext('web')->keys())->toBe(PermissionPolicies::universe()->keys());
    });

    it('adds code-declared rules on top of the config', function (): void {
        configurePolicies(['web' => ['hide' => ['resources' => [RoleResource::class]]]]);

        PermissionPolicies::context('web')->hidePermissions(['Delete:Order']);

        $web = PermissionPolicies::forContext('web');

        expect($web->hasGroup(RoleResource::class))->toBeFalse()
            ->and($web->contains('Delete:Order'))->toBeFalse();
    });

    it('drives the role form', function (): void {
        configurePolicies(['admin' => [], 'web' => ['hide' => ['resources' => [RoleResource::class], 'actions' => ['forceDelete']]]]);

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret']));

        $roleList = RoleResource::getPermissionGroupStateName(groupOf(PermissionPolicies::universe(), RoleResource::class));
        $serviceList = RoleResource::getPermissionGroupStateName(groupOf(PermissionPolicies::universe(), ServiceResource::class));

        Livewire::test(CreateRole::class)
            ->fillForm(['guard_name' => 'web'])
            ->assertFormFieldDoesNotExist($roleList)
            ->assertFormFieldExists($serviceList, fn (CheckboxList $field): bool => ! array_key_exists('ForceDelete:Service', $field->getOptions()))
            ->fillForm(['guard_name' => 'admin'])
            ->assertFormFieldExists($roleList);
    });
});

describe('invalid config', function (): void {
    it('rejects what it does not understand', function (array $guards, string $message): void {
        configurePolicies($guards);

        expect(fn () => app(PolicyRegistry::class))->toThrow(InvalidPolicyConfiguration::class, $message);
    })->with([
        'unknown section' => [['web' => ['hidden' => []]], 'Unknown key [hidden] in [filament-permission-policies.guards.web]'],
        'unknown rule type' => [['web' => ['hide' => ['resource' => [RoleResource::class]]]], 'Unknown key [resource] in [filament-permission-policies.guards.web.hide]'],
        'type not allowed in section' => [['web' => ['only' => ['kinds' => ['page']]]], 'Unknown key [kinds] in [filament-permission-policies.guards.web.only]'],
        'not a list' => [['web' => ['hide' => ['resources' => RoleResource::class]]], '[filament-permission-policies.guards.web.hide.resources] must be a list of strings'],
        'unknown kind' => [['web' => ['hide' => ['kinds' => ['tab']]]], 'Unknown kind [tab]'],
        'misspelled class' => [['web' => ['hide' => ['resources' => ['App\\Filament\\Resources\\RoleResourse']]]], 'Class [App\\Filament\\Resources\\RoleResourse] in [filament-permission-policies.guards.web.hide.resources] does not exist'],
        'unknown guard' => [['sellers' => []], 'Guard [sellers] is configured for roles but is not defined in config/auth.php'],
    ]);

    it('rejects an unknown global section', function (): void {
        configurePolicies([], ['hide' => ['widget' => []]]);

        expect(fn () => app(PolicyRegistry::class))->toThrow(InvalidPolicyConfiguration::class, 'filament-permission-policies.global.hide');
    });
});
