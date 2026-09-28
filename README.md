# Filament Permission Policies

Context-aware permission presentation for [Filament Shield](https://filamentphp.com/plugins/bezhansalleh-shield).

Shield generates every permission your panel needs. Not every role should be
offered all of them. A member role on the public site has no business with
the role resource or with `forceDelete`; an administrator role needs the lot.

This package sits between Filament and Shield and answers one question:

> Given this role, which resources, models and permissions should its form present?

```text
Filament role form
      │   renders one filtered catalog
Permission Policies   ◄── contexts + rules
      │   reads the universe
Filament Shield
      │
Spatie Laravel Permission ─► database
```

Hidden permissions are removed, not disabled: no empty sections, no greyed-out
checkboxes, and every count on the form is computed from what is shown.

- [Why it exists](#why-it-exists)
- [Installation](#installation)
- [Configuration](#configuration)
- [Contexts](#contexts)
- [Resource rules](#resource-rules)
- [Model rules](#model-rules)
- [Permission rules](#permission-rules)
- [Rule precedence](#rule-precedence)
- [Filament integration](#filament-integration)
- [Shield integration](#shield-integration)
- [Extending the package](#extending-the-package)
- [Visibility is not authorization](#visibility-is-not-authorization)
- [Testing](#testing)

## Why it exists

Shield builds a permission for every policy method of every resource, and its
role form shows all of them. Applications that manage several kinds of roles
end up patching the role form with conditions such as "if the guard is `web`,
hide this section". Those conditions spread across closures, disagree about
counts, and break when the permission key format changes.

This package moves those decisions into declared rules:

- **Shield** stays responsible for discovering entities, building keys and
  storing permissions. Nothing is forked or copied.
- **This package** decides what each context presents, in one place, with a
  documented precedence.
- **Your policies** stay responsible for authorization.

## Requirements

- PHP 8.4+
- Laravel 13+
- Filament 5
- Filament Shield 4.3+

## Installation

```bash
composer require syriable/filament-permission-policies
```

The service provider is discovered automatically. There is no config file and
no migration.

If you have not published Shield's role resource yet, do it now; the package
plugs into your copy of it:

```bash
php artisan shield:publish
```

Add the trait to your role resource and the page traits to its create and
edit pages:

```php
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Syriable\Filament\Plugins\PermissionPolicies\Filament\Concerns\HasPermissionPolicies;

class RoleResource extends ShieldRoleResource
{
    use HasPermissionPolicies;

    // ...
}
```

```php
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\CreateRole as ShieldCreateRole;
use Syriable\Filament\Plugins\PermissionPolicies\Filament\Concerns\ScopesPermissionsOnCreate;

class CreateRole extends ShieldCreateRole
{
    use ScopesPermissionsOnCreate;

    protected static string $resource = RoleResource::class;
}
```

```php
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole as ShieldEditRole;
use Syriable\Filament\Plugins\PermissionPolicies\Filament\Concerns\ScopesPermissionsOnSave;

class EditRole extends ShieldEditRole
{
    use ScopesPermissionsOnSave;

    protected static string $resource = RoleResource::class;
}
```

Until you declare a rule, every role sees exactly what Shield generates.

## Configuration

Rules are declared in code, usually in a service provider's `boot()` method,
with the `PermissionPolicies` facade:

```php
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;

public function boot(): void
{
    PermissionPolicies::context('web')
        ->hideResources([RoleResource::class])
        ->hideActions(['restore', 'restoreAny', 'forceDelete', 'forceDeleteAny', 'replicate', 'reorder']);
}
```

Rules that apply to every context go on the global policy:

```php
PermissionPolicies::global()->hideKinds([GroupKind::Widget]);
```

Every method returns the policy, so rules chain. The order you declare them in
never changes the result (see [Rule precedence](#rule-precedence)).

## Contexts

A context is a name for the audience a role belongs to: `admin`, `web`,
`seller`, `moderator`, anything. Each context has its own policy, created the
first time you mention it:

```php
PermissionPolicies::context('seller')
    ->onlyResources([ServiceResource::class, OrderResource::class])
    ->hideActions(['forceDelete']);

PermissionPolicies::context('moderator')
    ->onlyActions(['viewAny', 'view', 'update']);
```

A context with no rules, such as `admin` above, presents the whole universe.
Adding a context never requires touching the role form.

### How a role gets its context

By default the context is the role's guard: a `web` role is managed in the
`web` context, an `admin` role in the `admin` context. The form's current
value wins over the stored one, so changing the guard on the form switches
the permissions shown immediately (make the guard field `->live()`).

To map roles differently, bind your own resolver:

```php
use Illuminate\Database\Eloquent\Model;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\ContextResolver;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;

final class RoleTypeContextResolver implements ContextResolver
{
    public function resolve(array $state = [], ?Model $role = null): PermissionContext
    {
        $type = $state['type'] ?? $role?->getAttribute('type') ?? 'staff';

        return PermissionContext::make($type, ['role' => $role]);
    }
}

// In a service provider's register() method:
$this->app->bind(ContextResolver::class, RoleTypeContextResolver::class);
```

`$state` is the role form's current data; `$role` is the stored role, or
`null` while one is being created. Anything you put in the context's
attributes is available to custom rules.

### Reading a context

```php
$catalog = PermissionPolicies::forContext('web');
$catalog = PermissionPolicies::forRole($role);          // resolves the context
$universe = PermissionPolicies::universe();             // before any rule
```

A `PermissionCatalog` is immutable and never contains an empty group:

```php
$catalog->groups(GroupKind::Resource);   // list<PermissionGroup>
$catalog->group(ServiceResource::class); // ?PermissionGroup
$catalog->models();                      // models behind the resources
$catalog->keys();                        // every permission key
$catalog->options(GroupKind::Page);      // [key => label]
$catalog->groupCount(GroupKind::Resource);
$catalog->permissionCount();
$catalog->selectedCount($role->permissions->pluck('name'));
$catalog->intersect($submittedKeys);     // only keys this context manages
```

## Resource rules

```php
PermissionPolicies::context('web')
    ->hideResources([RoleResource::class, UserResource::class])
    ->onlyResources([ServiceResource::class, OrderResource::class]) // or an allow-list
    ->allowResources([ReportResource::class]);                      // re-show a globally hidden one

PermissionPolicies::context('web')
    ->hidePages([Settings::class])
    ->hideWidgets([RevenueChart::class])
    ->hideKinds([GroupKind::Widget, GroupKind::Custom]);             // whole tabs
```

`onlyResources()` affects resources only; pages, widgets and custom
permissions keep their own rules.

## Model rules

When several resources manage the same model, target the model:

```php
PermissionPolicies::context('web')->hideModels([User::class]);   // every resource of User
PermissionPolicies::context('web')->onlyModels([Service::class, Order::class]);
PermissionPolicies::context('support')->allowModels([Ticket::class]);
```

## Permission rules

Actions are the policy methods Shield generates permissions from. They are
matched in any case (`forceDelete`, `ForceDelete` and `force_delete` are the
same), so rules keep working when you change Shield's key format.

```php
PermissionPolicies::context('web')
    ->hideActions(['forceDelete', 'forceDeleteAny', 'reorder'])  // on every resource
    ->onlyActions(['viewAny', 'view', 'create', 'update'])       // allow-list
    ->hidePermissions(['Delete:Order'])                          // exact keys
    ->allowPermissions(['Reorder:Service']);
```

Action rules apply to resources. Pages and widgets have a single permission
each; hide them by class or kind.

To combine dimensions, use a `Target`. Every dimension you set must match:

```php
use Syriable\Filament\Plugins\PermissionPolicies\Rules\Target;

PermissionPolicies::context('seller')
    ->deny(Target::make()->groups([ServiceResource::class])->actions(['delete', 'deleteAny']));
```

For anything else, write a condition. It receives the permission, its group
and the context:

```php
PermissionPolicies::context('seller')->hideWhen(
    fn (PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): bool =>
        $group->kind === GroupKind::Resource && ! $context->attribute('role')?->is_verified,
);
```

`onlyPermissions([...])` is the strictest allow-list: nothing of any kind is
shown except the listed keys.

## Rule precedence

Every rule answers **allow**, **deny** or **abstain** for each permission.

1. **The context decides first.** If the context's rules allow or deny, that
   is final.
2. **The global scope decides next,** only when the context abstains.
3. **Otherwise the permission is shown.**

Inside one scope, **deny wins over allow**. Declaration order never matters.

| Global | Context | Result |
|---|---|---|
| — | — | shown |
| deny | — | hidden |
| deny | allow | shown |
| allow | deny | hidden |
| — | deny + allow | hidden |
| allow | deny `forceDelete` + allow `ServiceResource` | `ForceDelete:Service` hidden |

Allow-lists (`only*`) never *allow*; they deny what they do not list and
abstain on the rest. So a context's `onlyResources()` narrows what it shows
but never brings back a permission the global scope hides. To bring one back,
use an explicit `allow*` rule on the context.

Resource, model, action and permission rules are not ranked against each
other. When you need an exception, target it precisely instead of relying on
one kind of rule beating another.

## Filament integration

`HasPermissionPolicies` replaces Shield's `getShieldFormComponents()` and
`getSelectAllFormComponent()`, so both Shield's own form and a custom form
that calls these methods render from the catalog of the role being edited:

- one section per presented resource, one list per page/widget/custom tab;
- tab badges equal the number of presented permissions;
- a tab with nothing to present is not rendered;
- "select all" ticks exactly the presented permissions;
- Shield's tab switches, simple resource view and column settings still apply.

For a roles table, count what a role's context presents rather than every row
in `role_has_permissions`:

```php
TextColumn::make('permissions_count')
    ->state(fn (Role $record): int => RoleResource::countPresentedPermissions($record)),
```

(Eager load `permissions` on the table query to avoid one query per row.)

To change how a resource section looks, override
`getPermissionGroupSection(PermissionGroup $group)` on your role resource; the
checkbox list itself comes from `getPermissionCheckboxList()`.

### Saving

`ScopesPermissionsOnCreate` and `ScopesPermissionsOnSave` drop every submitted
key the role's context does not present before Shield syncs the role. As a
result:

- a crafted request cannot attach a hidden permission;
- a role saved in a narrower context (for example after changing its guard)
  keeps only the permissions that context manages. This is deliberate: the
  form is the full description of the role in its context.

## Shield integration

`ShieldPermissionSource` builds the universe from Shield's public API:
`FilamentShield::getResources()`, `getPages()`, `getWidgets()` and
`getCustomPermissions()`. Everything Shield is configured with is honoured:
excluded resources, pages and widgets, per-resource policy methods
(`resources.manage`), key case and separator, custom permissions, and
discovery across panels.

The rest of the package never reads Shield directly. To take permissions from
somewhere else, bind another source (see below).

## Extending the package

**A rule class.** Implement one method; return `Decision::Abstain` for
permissions the rule is not about:

```php
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionRule;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\Decision;

final readonly class HideSensitiveModels implements PermissionRule
{
    public function decide(PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): Decision
    {
        return is_a($group->model, Sensitive::class, true) ? Decision::Deny : Decision::Abstain;
    }
}

PermissionPolicies::context('web')->rule(new HideSensitiveModels);
```

**Extra permissions.** Merge your own groups into Shield's universe:

```php
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Shield\ShieldPermissionSource;

final readonly class ApplicationPermissionSource implements PermissionSource
{
    public function __construct(private ShieldPermissionSource $shield) {}

    public function catalog(): PermissionCatalog
    {
        return $this->shield->catalog()->merge(new PermissionCatalog([
            new PermissionGroup('billing', GroupKind::Custom, 'Billing', permissions: [
                new PermissionDefinition('Refund:Order', 'refund', 'Refund orders'),
            ]),
        ]));
    }
}

$this->app->bind(PermissionSource::class, ApplicationPermissionSource::class);
```

Groups and permissions carry a `meta` array for anything your UI needs.

**A different context mapping.** Bind a `ContextResolver` (see
[Contexts](#how-a-role-gets-its-context)).

## Visibility is not authorization

This package decides what a role form **presents**. It does not register a
gate, a policy or a `before` callback, and it never grants anything.

- A hidden permission still exists if Shield generated it.
- A role that somehow holds a hidden permission is still authorized by it
  until the role is saved; your policies decide what it means.
- A shown permission is only a checkbox.

Keep authorization in your policies. For example, a member role holding
`Update:User` should still be refused by a `UserPolicy` that only honours
administrators.

## Testing

```bash
composer test      # Pest
composer analyse   # PHPStan, level 9
composer format    # Pint
composer refactor  # Rector
```

Rules can be tested without Shield by binding a fixed universe before the
manager is first resolved in the test:

```php
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Sources\InMemoryPermissionSource;

app()->instance(PermissionSource::class, new InMemoryPermissionSource($catalog));

expect(PermissionPolicies::forContext('web')->contains('ForceDelete:Service'))->toBeFalse();
```

See [docs/architecture.md](docs/architecture.md) for the design, the
behaviour this package was extracted from, and the review notes.

## License

MIT. See [LICENSE.md](LICENSE.md).
