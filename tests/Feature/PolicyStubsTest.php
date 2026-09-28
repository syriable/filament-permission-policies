<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;
use Syriable\Filament\Plugins\PermissionPolicies\PermissionManager;
use Syriable\Filament\Plugins\PermissionPolicies\PolicyRegistry;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Service;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\User;

const GENERATED_POLICY = __DIR__.'/../Fixtures/Policies/ServicePolicy.php';

beforeEach(function (): void {
    // Roles on the web guard hide force delete; administrator roles see all.
    config()->set('filament-permission-policies.guards', [
        'admin' => [],
        'web' => ['hide' => ['actions' => ['forceDelete']]],
    ]);

    app()->forgetInstance(PolicyRegistry::class);
    app()->forgetInstance(PermissionManager::class);
    PermissionPolicies::clearResolvedInstance(PermissionManager::class);
});

afterEach(function (): void {
    File::deleteDirectory(dirname(GENERATED_POLICY));
    File::deleteDirectory(base_path('stubs/filament-shield'));
});

/**
 * @param  list<string>  $permissions
 */
function webUserWith(array $permissions): User
{
    $user = User::query()->create(['name' => 'Member', 'email' => fake()->unique()->safeEmail(), 'password' => 'secret']);
    $role = Role::query()->create(['name' => 'member-'.fake()->unique()->numberBetween(), 'guard_name' => 'web']);

    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $user->assignRole($role);

    return $user;
}

describe('outside the panel', function (): void {
    it('counts a permission the guard presents and the user holds', function (): void {
        expect(PermissionPolicies::allowsOutsidePanel(webUserWith(['View:Service']), 'View:Service'))->toBeTrue();
    });

    it('refuses a permission the user does not hold', function (): void {
        expect(PermissionPolicies::allowsOutsidePanel(webUserWith([]), 'View:Service'))->toBeFalse();
    });

    it('refuses a permission the guard hides, even when the user holds it', function (): void {
        $user = webUserWith(['ForceDelete:Service']);

        expect($user->can('ForceDelete:Service'))->toBeTrue()
            ->and(PermissionPolicies::allowsOutsidePanel($user, 'ForceDelete:Service'))->toBeFalse();
    });

    it('refuses every permission of a guard roles are not configured for', function (): void {
        config()->set('filament-permission-policies.guards', ['admin' => []]);
        app()->forgetInstance(PolicyRegistry::class);
        app()->forgetInstance(PermissionManager::class);
        PermissionPolicies::clearResolvedInstance(PermissionManager::class);

        expect(PermissionPolicies::allowsOutsidePanel(webUserWith(['View:Service']), 'View:Service'))->toBeFalse();
    });

    it('checks only the guard for a permission Shield does not generate', function (): void {
        expect(PermissionPolicies::allowsOutsidePanel(webUserWith(['Export:Reports']), 'Export:Reports'))->toBeTrue();
    });
});

describe('policy stubs', function (): void {
    it('publishes the stubs where Shield reads them', function (): void {
        Artisan::call('vendor:publish', ['--tag' => 'filament-permission-policies-stubs']);

        foreach (['DefaultPolicy', 'AuthenticatablePolicy', 'SingleParamMethod', 'MultiParamMethod'] as $stub) {
            expect(base_path("stubs/filament-shield/{$stub}.stub"))->toBeFile();
        }
    });

    it('makes shield:generate write policies that split inside and outside the panel', function (): void {
        Artisan::call('vendor:publish', ['--tag' => 'filament-permission-policies-stubs']);
        Artisan::call('shield:generate', ['--resource' => 'ServiceResource', '--option' => 'policies', '--panel' => 'admin', '--no-interaction' => true]);

        $source = File::get(GENERATED_POLICY);

        expect($source)
            ->toContain('use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;')
            ->toContain("? \$authUser->can('ForceDelete:Service')")
            ->toContain(": PermissionPolicies::allowsOutsidePanel(\$authUser, 'ForceDelete:Service')");

        require_once GENERATED_POLICY;
        Gate::policy(Service::class, 'Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Policies\ServicePolicy');

        $user = webUserWith(['View:Service', 'ForceDelete:Service']);
        $service = new Service;

        Filament::setServingStatus(false);

        expect($user->can('view', $service))->toBeTrue()
            ->and($user->can('forceDelete', $service))->toBeFalse();

        Filament::setServingStatus(true);

        expect($user->can('forceDelete', $service))->toBeTrue();

        Filament::setServingStatus(false);
    });
});
