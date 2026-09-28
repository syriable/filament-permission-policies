<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\User;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\Pages\CreateRole;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\Pages\EditRole;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\RoleResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\ServiceResource;

const SERVICE_LIST = 'syriable_filament_plugins_permissionpolicies_tests_fixtures_resources_serviceresource';
const ROLE_LIST = 'syriable_filament_plugins_permissionpolicies_tests_fixtures_resources_roles_roleresource';
const USER_LIST = 'syriable_filament_plugins_permissionpolicies_tests_fixtures_resources_userresource';

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');

    $this->actingAs(User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret']));

    // The member context of the application this package was extracted from.
    PermissionPolicies::context('web')
        ->hideResources([RoleResource::class])
        ->hideActions(['restore', 'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder']);
});

it('names each resource list after its resource', function (): void {
    $group = groupOf(PermissionPolicies::universe(), ServiceResource::class);

    expect(RoleResource::getPermissionGroupStateName($group))->toBe(SERVICE_LIST);
});

it('shows every resource and permission for an administrator role', function (): void {
    Livewire::test(CreateRole::class)
        ->fillForm(['guard_name' => 'admin'])
        ->assertFormFieldExists(ROLE_LIST)
        ->assertFormFieldExists(SERVICE_LIST, fn (CheckboxList $field): bool => count($field->getOptions()) === 12
            && array_key_exists('ForceDelete:Service', $field->getOptions()));
});

it('removes hidden resources and permissions for a member role', function (): void {
    Livewire::test(CreateRole::class)
        ->fillForm(['guard_name' => 'web'])
        ->assertFormFieldDoesNotExist(ROLE_LIST)
        ->assertFormFieldExists(USER_LIST)
        ->assertFormFieldExists(SERVICE_LIST, fn (CheckboxList $field): bool => array_keys($field->getOptions()) === [
            'ViewAny:Service', 'View:Service', 'Create:Service', 'Update:Service', 'Delete:Service', 'DeleteAny:Service',
        ]);
});

it('switches the context when the guard changes on the form', function (): void {
    Livewire::test(CreateRole::class)
        ->fillForm(['guard_name' => 'web'])
        ->assertFormFieldDoesNotExist(ROLE_LIST)
        ->fillForm(['guard_name' => 'admin'])
        ->assertFormFieldExists(ROLE_LIST);
});

it('selects only the presented permissions with "select all"', function (): void {
    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'member', 'guard_name' => 'web'])
        ->fillForm(['select_all' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::findByName('member', 'web');

    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(PermissionPolicies::forContext('web')->keys())
        ->and($role->permissions)->toHaveCount(3 * 6 + 2)
        ->and(Permission::query()->where('name', 'ForceDelete:Service')->exists())->toBeFalse();
});

it('marks "select all" once every presented permission is ticked', function (): void {
    $component = Livewire::test(CreateRole::class)->fillForm(['guard_name' => 'web']);

    foreach (RoleResource::getPermissionCheckboxListOptions(PermissionPolicies::forContext('web')) as $name => $options) {
        $component->fillForm([$name => array_keys($options)]);
    }

    $component->assertSchemaStateSet(['select_all' => true]);
});

it('rejects a hidden permission a crafted request submits', function (): void {
    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'member', 'guard_name' => 'web'])
        ->set('data.'.SERVICE_LIST, ['View:Service', 'ForceDelete:Service'])
        ->call('create')
        ->assertHasFormErrors([SERVICE_LIST.'.1']);

    expect(Role::query()->where('name', 'member')->exists())->toBeFalse();
});

it('drops keys outside the context before Shield creates the role', function (): void {
    /** @var CreateRole $page */
    $page = Livewire::test(CreateRole::class)->instance();

    $data = (fn (): array => $this->mutateFormDataBeforeCreate([
        'name' => 'member',
        'guard_name' => 'web',
        'services' => ['View:Service', 'ForceDelete:Service'],
        'roles' => ['Delete:Role'],
        'not_a_permission' => 'anything',
    ]))->call($page);

    expect($data)->toBe(['name' => 'member', 'guard_name' => 'web'])
        ->and($page->permissions->all())->toBe(['View:Service']);
});

it('drops keys outside the context before Shield saves the role', function (): void {
    $role = Role::query()->create(['name' => 'staff', 'guard_name' => 'admin']);
    /** @var EditRole $page */
    $page = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])->instance();

    (fn (): array => $this->mutateFormDataBeforeSave([
        'name' => 'staff',
        'guard_name' => 'web',
        'resources' => ['View:Service', 'Delete:Role', 'Reorder:Order'],
    ]))->call($page);

    expect($page->permissions->all())->toBe(['View:Service']);
});

it('shows and keeps only the presented permissions of a stored role', function (): void {
    $role = Role::query()->create(['name' => 'member', 'guard_name' => 'web']);
    $role->givePermissionTo(
        Permission::create(['name' => 'View:Service', 'guard_name' => 'web']),
        Permission::create(['name' => 'Reorder:Service', 'guard_name' => 'web']),
    );

    Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
        ->assertSchemaStateSet([SERVICE_LIST => ['View:Service']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->fresh()?->permissions->pluck('name')->all())->toBe(['View:Service']);
});

it('drops permissions a narrower context does not manage when the guard changes', function (): void {
    $role = Role::query()->create(['name' => 'staff', 'guard_name' => 'admin']);
    $role->givePermissionTo(
        Permission::create(['name' => 'View:Service', 'guard_name' => 'admin']),
        Permission::create(['name' => 'Delete:Role', 'guard_name' => 'admin']),
    );

    Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
        ->assertSchemaStateSet([ROLE_LIST => ['Delete:Role']])
        ->fillForm(['guard_name' => 'web'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->fresh()?->guard_name)->toBe('web')
        ->and($role->fresh()?->permissions->pluck('name')->all())->not->toContain('Delete:Role');
});

it("counts a stored role's permissions the way its context presents them", function (): void {
    $role = Role::query()->create(['name' => 'member', 'guard_name' => 'web']);
    $role->givePermissionTo(
        Permission::create(['name' => 'View:Service', 'guard_name' => 'web']),
        Permission::create(['name' => 'Reorder:Service', 'guard_name' => 'web']),
        Permission::create(['name' => 'Delete:Role', 'guard_name' => 'web']),
    );

    expect(RoleResource::countPresentedPermissions($role))->toBe(1);
});

it('builds every checkbox list from the same catalog the badges count', function (string $guard): void {
    $catalog = PermissionPolicies::forContext($guard);
    $lists = RoleResource::getPermissionCheckboxListOptions($catalog);

    expect(array_sum(array_map(count(...), $lists)))->toBe($catalog->permissionCount())
        ->and(array_keys($lists))->toContain(USER_LIST);
})->with(['web', 'admin']);

it('leaves out the lists of tabs Shield has switched off', function (): void {
    config()->set('filament-shield.shield_resource.tabs.widgets', false);

    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'member', 'guard_name' => 'web'])
        ->assertFormFieldDoesNotExist('widgets_tab')
        ->assertFormFieldExists('pages_tab')
        ->fillForm(['select_all' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Role::findByName('member', 'web')->permissions->pluck('name')->all())->not->toContain('View:SalesChart');
});

it("renders one resource list in Shield's simple view", function (): void {
    FilamentShieldPlugin::get()->simpleResourcePermissionView();

    expect(array_keys(RoleResource::getPermissionCheckboxListOptions(PermissionPolicies::forContext('web'))))
        ->toBe(['resources_tab', 'pages_tab', 'widgets_tab']);

    Livewire::test(CreateRole::class)
        ->fillForm(['guard_name' => 'web'])
        ->assertFormFieldDoesNotExist(SERVICE_LIST)
        ->assertFormFieldExists('resources_tab', fn (CheckboxList $field): bool => count($field->getOptions()) === 3 * 6);
});
