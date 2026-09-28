# Filament Permission Policies

Context-aware permission presentation for [Filament Shield](https://filamentphp.com/plugins/bezhansalleh-shield).

Shield generates every permission your panel needs. Not every role should be
offered all of them. A member role on the public site has no business with the
role resource or with `forceDelete`; an administrator role needs the lot; an
API role needs neither pages nor widgets.

This package sits on top of Shield and answers one question:

> Given this role, which resources, models and permissions should its form present?

```text
Filament role form        renders one filtered catalog: sections, checkboxes, counts
        │
Permission Policies       guards/contexts + rules  (this package)
        │
Filament Shield           discovers entities, builds permission keys
        │
Spatie Laravel Permission ─► database
```

Hidden permissions are **removed**, not disabled: no empty sections, no
greyed-out checkboxes, and every count on the form is computed from what is
shown. You control all of it from one config file.

> [!IMPORTANT]
> **This package is a layer on top of Filament Shield. It does not replace it.**
> Shield must be installed, set up and working in your panel **before** you
> install this package. Read the
> [Filament Shield documentation](https://filamentphp.com/plugins/bezhansalleh-shield)
> and run its installation commands first (see
> [Step 1](#step-1-install-and-set-up-filament-shield)). Everything this package
> shows comes from what Shield generates; if Shield is not set up, there is
> nothing to present.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
  - [Step 1: Install and set up Filament Shield](#step-1-install-and-set-up-filament-shield)
  - [Step 2: Install this package](#step-2-install-this-package)
  - [Step 3: Wire up the role resource](#step-3-wire-up-the-role-resource)
  - [Step 4: Declare your guards and rules](#step-4-declare-your-guards-and-rules)
- [How it works](#how-it-works)
- [Configuration](#configuration)
- [Contexts](#contexts)
- [Resource, model and permission rules](#resource-model-and-permission-rules)
- [Rule precedence](#rule-precedence)
- [The role form](#the-role-form)
- [The roles table](#the-roles-table)
- [Multiple guards](#multiple-guards)
- [Generated policies](#generated-policies)
- [Shield integration](#shield-integration)
- [Extending the package](#extending-the-package)
- [Visibility is not authorization](#visibility-is-not-authorization)
- [Troubleshooting](#troubleshooting)
- [Testing](#testing)

## Requirements

| | Version |
|---|---|
| PHP | 8.4+ |
| Laravel | 13+ |
| Filament | 5.9+ |
| [Filament Shield](https://github.com/bezhanSalleh/filament-shield) | 4.3.1+ (installed **and set up**) |
| [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) | pulled in by Shield |

## Installation

### Step 1: Install and set up Filament Shield

Skip this step only if Shield already works in your panel: you can open the
Roles page, and the permissions of your resources are listed there.

Otherwise, follow the
[Filament Shield installation guide](https://filamentphp.com/plugins/bezhansalleh-shield).
It is the reference for these commands and their options; in short:

```bash
composer require bezhansalleh/filament-shield

# Publishes Shield's config, runs Spatie's permission migrations,
# and checks your user model.
php artisan shield:setup

# Registers the Shield plugin on your panel.
php artisan shield:install admin          # your panel ID

# Generates the permissions (and, if you want, the policies) for every
# resource, page and widget of the panel.
php artisan shield:generate --all --panel=admin

# Gives a user the super admin role.
php artisan shield:super-admin --user=1 --panel=admin
```

Your user model must use Spatie's `HasRoles` trait:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Then publish Shield's role resource into your application. This package plugs
into **your copy** of it:

```bash
php artisan shield:publish
```

> [!NOTE]
> Re-run `php artisan shield:generate` whenever you add a resource, page or
> widget. This package only presents permissions that Shield has generated.

### Step 2: Install this package

```bash
composer require syriable/filament-permission-policies
```

The service provider is discovered automatically. There is no migration.
Publish the config file, which is where you will declare your guards and rules:

```bash
php artisan vendor:publish --tag="filament-permission-policies-config"
```

Until you declare a rule, every role sees exactly what Shield generates.

If Shield generates your policies, also publish this package's policy stubs
and generate the policies again (see [Generated policies](#generated-policies)):

```bash
php artisan vendor:publish --tag="filament-permission-policies-stubs"
php artisan shield:generate --all --option=policies --panel=admin
```

### Step 3: Wire up the role resource

Add the trait to the role resource you published in Step 1:

```php
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Syriable\Filament\Plugins\PermissionPolicies\Filament\Concerns\HasPermissionPolicies;

class RoleResource extends ShieldRoleResource
{
    use HasPermissionPolicies;
}
```

Add the page traits to its create and edit pages. They make sure a role is
never saved with a permission its context does not present:

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

Finally, use the package's guard field in the role form. It lists your
configured guards and keeps the rest of the form in line when the guard
changes (see [The role form](#the-role-form)):

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        Section::make()
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
                static::getGuardFormComponent(),
                static::getSelectAllFormComponent(),
            ])
            ->columns(3)
            ->columnSpanFull(),
        static::getShieldFormComponents(),
    ]);
}
```

### Step 4: Declare your guards and rules

In `config/filament-permission-policies.php`, list the guards roles can be
created for and what each one's role form hides:

```php
return [
    'guards' => [
        'admin' => [
            'label' => 'Administrator',
            'color' => 'primary',
            // No rules: administrators see everything Shield generates.
        ],

        'web' => [
            'label' => 'Member',
            'color' => 'gray',
            'hide' => [
                'resources' => [App\Filament\Resources\Roles\RoleResource::class],
                'actions' => ['restore', 'restoreAny', 'forceDelete', 'forceDeleteAny', 'replicate', 'reorder'],
            ],
        ],
    ],
];
```

Open the Roles page: an administrator role shows every resource and every
ability; switching the guard to `web` removes the role resource and those six
abilities, and the counts follow.

## How it works

Four ideas, in the order the package applies them:

1. **The universe.** Every permission Shield generates, grouped by entity: one
   group per resource, page and widget, plus one for custom permissions.
2. **The context.** Who the role is for. By default a role's context is its
   guard, so a `web` role is managed in the `web` context.
3. **The rules.** What each context hides, allows or is limited to, from the
   config file and from code.
4. **The catalog.** The universe filtered by the context's rules. The form,
   the tabs, the badges, "select all", the table counts and the save step all
   read this one catalog, so they can never disagree.

```text
universe ─► apply the context's rules ─► catalog ─► form, counts, saving
```

## Configuration

### The config file

```php
return [
    'guards' => [
        'admin' => [
            'label' => 'roles.guards.admin',   // translation key or plain text
            'color' => 'primary',              // any Filament color
        ],
        'web' => [
            'label' => 'roles.guards.web',
            'color' => 'gray',
            'hide' => [
                'resources' => [RoleResource::class],
                'actions' => ['restore', 'restoreAny', 'forceDelete', 'forceDeleteAny', 'replicate', 'reorder'],
            ],
        ],
        'api' => [
            'label' => 'API',
            'color' => 'info',
            'hide' => ['kinds' => ['page', 'widget']],
            'only' => ['actions' => ['viewAny', 'view', 'create', 'update', 'delete']],
        ],
    ],

    // Applied to every guard. A guard's own "allow" brings something back.
    'global' => [
        'hide' => ['models' => [App\Models\AuditLog::class]],
    ],
];
```

Each guard entry takes:

| Key | Meaning |
|---|---|
| `label` | A translation key or plain text. Defaults to the guard name in title case. |
| `color` | A Filament color for the guard badge. Defaults to `gray`. |
| `hide` | Removed from this guard's role form. |
| `only` | When set, nothing else of that type is shown. |
| `allow` | Shown even when the `global` section hides it. |

And each of `hide`, `only` and `allow` takes lists of:

| Rule type | Values | `hide` | `only` | `allow` |
|---|---|:-:|:-:|:-:|
| `resources` | Resource classes | ✓ | ✓ | ✓ |
| `pages` | Page classes | ✓ | | ✓ |
| `widgets` | Widget classes | ✓ | | ✓ |
| `models` | Model classes: every resource that manages them | ✓ | ✓ | ✓ |
| `actions` | Resource abilities: `forceDelete`, `reorder`, … | ✓ | ✓ | ✓ |
| `permissions` | Exact keys: `Delete:Order`, … | ✓ | ✓ | ✓ |
| `kinds` | Whole tabs: `resource`, `page`, `widget`, `custom` | ✓ | | |

The file is validated when it is loaded. An unknown section or rule type, a
class that does not exist, an unknown kind, or a guard missing from
`config/auth.php` throws `InvalidPolicyConfiguration` with the exact config
path, so a typo never silently shows what was meant to be hidden. An empty
list is ignored rather than read as "hide everything". With no guards
configured, every guard in `config/auth.php` is offered, with no rules.

> [!TIP]
> If you cache your configuration in production, run `php artisan config:cache`
> again after changing this file.

### Rules in code

Everything the config file does can also be written in code, usually in a
service provider's `boot()` method. Code rules are added on top of the config
file's:

```php
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;

public function boot(): void
{
    PermissionPolicies::context('web')
        ->hideResources([RoleResource::class])
        ->hideActions(['forceDelete', 'reorder']);

    PermissionPolicies::global()->hideKinds([GroupKind::Widget]);
}
```

Use code for rules the config file cannot express, such as conditions (see
[`hideWhen()`](#conditions)). Every method returns the policy, so rules chain;
the order you declare them in never changes the result.

## Contexts

A context is a name for the audience a role belongs to: `admin`, `web`,
`api`, `seller`, anything. Each has its own rules. A context with no rules
presents the whole universe. Adding a context never requires touching the role
form.

### How a role gets its context

By default, the context is the role's guard. The form's current value wins
over the stored one, so changing the guard on the form switches the
permissions shown immediately.

To map roles to contexts differently, for example by a `type` column, bind
your own resolver in a service provider's `register()` method:

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

$this->app->bind(ContextResolver::class, RoleTypeContextResolver::class);
```

`$state` is the role form's current data; `$role` is the stored role, or
`null` while one is being created. Whatever you put in the context's
attributes is available to [conditions](#conditions) and custom rules.

### Reading a catalog

```php
$catalog = PermissionPolicies::forContext('web');   // a context by name
$catalog = PermissionPolicies::forRole($role);       // a role's context
$universe = PermissionPolicies::universe();          // before any rule
```

A `PermissionCatalog` is immutable and never contains an empty group:

```php
$catalog->groups(GroupKind::Resource);   // list<PermissionGroup>
$catalog->group(ServiceResource::class); // ?PermissionGroup
$catalog->models();                      // the models behind the resources
$catalog->keys();                        // every permission key
$catalog->options(GroupKind::Page);      // [key => label]
$catalog->groupCount(GroupKind::Resource);
$catalog->permissionCount();
$catalog->selectedCount($role->permissions->pluck('name'));
$catalog->intersect($submittedKeys);     // only the keys this context manages
```

## Resource, model and permission rules

Each example shows the config file, then the same rule in code.

### Resources, pages and widgets

```php
'hide' => ['resources' => [RoleResource::class], 'pages' => [Settings::class], 'widgets' => [RevenueChart::class]],
'only' => ['resources' => [ServiceResource::class, OrderResource::class]],
```

```php
PermissionPolicies::context('web')
    ->hideResources([RoleResource::class])
    ->hidePages([Settings::class])
    ->hideWidgets([RevenueChart::class])
    ->onlyResources([ServiceResource::class, OrderResource::class]);
```

`only.resources` affects resources only; pages, widgets and custom permissions
keep their own rules. To remove a whole tab, hide its kind:
`'hide' => ['kinds' => ['widget', 'custom']]`.

### Models

When several resources manage the same model, target the model:

```php
'hide' => ['models' => [App\Models\User::class]],   // every resource of User
```

```php
PermissionPolicies::context('web')->hideModels([User::class]);
```

### Actions and permissions

Actions are the policy methods Shield generates permissions from. They match
in any case (`forceDelete`, `ForceDelete` and `force_delete` are the same), so
rules keep working if you change Shield's key format. Action rules apply to
resources; pages and widgets have a single permission each, so hide them by
class or kind.

```php
'hide' => ['actions' => ['forceDelete', 'reorder'], 'permissions' => ['Delete:Order']],
'only' => ['actions' => ['viewAny', 'view', 'create', 'update']],
```

```php
PermissionPolicies::context('web')
    ->hideActions(['forceDelete', 'reorder'])
    ->hidePermissions(['Delete:Order'])
    ->onlyActions(['viewAny', 'view', 'create', 'update']);
```

`only.permissions` is the strictest allow-list: nothing of any kind is shown
except the listed keys.

### Combined targets

To hide one ability on one resource only, combine dimensions with a `Target`.
Every dimension you set must match:

```php
use Syriable\Filament\Plugins\PermissionPolicies\Rules\Target;

PermissionPolicies::context('seller')
    ->deny(Target::make()->groups([ServiceResource::class])->actions(['delete', 'deleteAny']));
```

### Conditions

For anything else, write a condition. It receives the permission, its group
and the context:

```php
PermissionPolicies::context('seller')->hideWhen(
    fn (PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): bool =>
        $group->kind === GroupKind::Resource && ! $context->attribute('role')?->is_verified,
);
```

## Rule precedence

Every rule answers **allow**, **deny** or **abstain** for each permission.

1. **The context decides first.** If its rules allow or deny, that is final.
2. **The global rules decide next,** only when the context abstains.
3. **Otherwise the permission is shown.**

Inside one context (or inside the global rules), **deny wins over allow**, and
declaration order never matters.

| Global | Context | Result |
|---|---|---|
| — | — | shown |
| hide | — | hidden |
| hide | allow | shown |
| allow | hide | hidden |
| — | hide + allow | hidden |
| allow | hide `forceDelete` + allow `ServiceResource` | `ForceDelete:Service` hidden |

`only` rules never *allow*: they hide what they do not list and abstain on the
rest. So a guard's `only.resources` narrows what it shows but never brings back
something the global rules hide. To bring something back, use `allow` on the
guard.

Resource, model, action and permission rules are not ranked against each other.
When you need an exception, target it precisely instead of relying on one kind
of rule beating another.

## The role form

`HasPermissionPolicies` replaces Shield's `getShieldFormComponents()` and
`getSelectAllFormComponent()`, so the form renders from the catalog of the role
being edited:

- one section per presented resource, one list per page, widget and custom tab;
- tab badges equal the number of presented permissions;
- a tab with nothing to present is not rendered;
- "select all" ticks exactly the presented permissions;
- Shield's tab switches, simple resource view and column settings still apply.

### The guard field

`getGuardFormComponent()` offers the guards from the config file (and rejects
any other). When the guard changes, it:

- drops ticks the new context does not present;
- fills lists that appear for the first time from the stored role's permissions;
- recalculates "select all".

If you keep your own guard field, make it `->live()` and call the same refresh,
or "select all" can be left stale after a guard change:

```php
Select::make('guard_name')
    ->options(PermissionPolicies::guards()->options())
    ->live()
    ->afterStateUpdated(fn (Select $component, Get $get, Set $set) => RoleResource::refreshPermissionFormState($component, $get, $set));
```

### Saving

`ScopesPermissionsOnCreate` and `ScopesPermissionsOnSave` drop every submitted
key the role's context does not present before Shield syncs the role:

- a crafted request cannot attach a hidden permission;
- a role saved in a narrower context (for example after changing its guard)
  keeps only the permissions that context manages. This is deliberate: the
  form is the full description of the role in its context.

### Customizing the form

Override `getPermissionGroupSection(PermissionGroup $group)` on your role
resource to change how a resource section looks; the checkbox list comes from
`getPermissionCheckboxList()`.

## The roles table

`PermissionPolicies::guards()` gives you the configured guards' labels and
colors, and `countPresentedPermissions()` counts what a role's context
presents rather than every row in `role_has_permissions`:

```php
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;

public static function configure(Table $table): Table
{
    $guards = PermissionPolicies::guards();

    return $table
        ->modifyQueryUsing(fn (Builder $query) => $query->with('permissions'))
        ->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('guard_name')
                ->badge()
                ->formatStateUsing(fn (string $state): string => $guards->label($state))
                ->color(fn (string $state): string => $guards->color($state)),
            TextColumn::make('permissions_count')
                ->state(fn (Role $record): string => sprintf(
                    '%d / %d',
                    RoleResource::countPresentedPermissions($record),
                    PermissionPolicies::forRole($record)->permissionCount(),
                )),
        ])
        ->filters([
            SelectFilter::make('guard_name')->options($guards->options()),
        ]);
}
```

Eager load `permissions` as above to avoid one query per row.

## Multiple guards

A role belongs to exactly one guard. Spatie Laravel Permission only lets a
model hold roles of its own guard. For every guard you list in the config file:

1. **Define the guard** in `config/auth.php`. The package refuses a guard that
   is not defined there.
2. **Give the model its guard.** A model whose roles use a guard other than the
   default sets it explicitly, for example
   `protected string $guard_name = 'admin';`.
3. **Know where permission rows come from.** `shield:generate` creates
   permission rows for your **panel's** guard. Rows for other guards are
   created by Shield when you save a role of that guard with those
   permissions ticked. You do not need to create them yourself.
4. **Treat role names as per guard.** Spatie allows an `editor` role on `web`
   and another on `api`. If your form checks names for uniqueness, scope that
   check to the guard.
5. **Be careful when changing a role's guard** once it is assigned. Users of
   the old guard can no longer hold it. Consider disabling the guard field on
   the edit page for roles that have users.

## Generated policies

Shield writes a policy for every resource's model with `shield:generate`. This
package ships its own versions of Shield's policy stubs, so those policies know
whether they are running inside the Filament panel or elsewhere.

```bash
php artisan vendor:publish --tag="filament-permission-policies-stubs"
```

This copies four files into your application's `stubs/filament-shield/`
directory, which is where Shield looks for custom stubs:

| Stub | Used for |
|---|---|
| `DefaultPolicy.stub` | The policy class of a model |
| `AuthenticatablePolicy.stub` | The policy class of the user model |
| `SingleParamMethod.stub` | Abilities without a record: `viewAny`, `create`, … |
| `MultiParamMethod.stub` | Abilities with a record: `view`, `update`, … |

Every generated ability then looks like this:

```php
public function forceDelete(AuthUser $authUser, Service $service): bool
{
    return filament()->isServing()
        ? $authUser->can('ForceDelete:Service')
        : PermissionPolicies::allowsOutsidePanel($authUser, 'ForceDelete:Service');
}
```

- **Inside the panel,** the permission is checked as Shield always did.
- **Outside the panel** (the public site, an API, a queued job),
  `allowsOutsidePanel()` counts the permission only when all three hold:
  1. the user's guard is one of the [configured guards](#the-config-file);
  2. that guard's role form presents the permission, per the config file's
     rules;
  3. the user holds the permission (`$user->can()`).

The result is that a permission can only take effect for users of a guard
whose role form could have granted it. A member (`web`) who holds
`ForceDelete:Service`, for example from an old role or a direct database edit,
is refused on the site if the `web` guard hides `forceDelete`. A permission
Shield does not generate (such as one created by hand) is checked against the
guard and `can()` only.

`allowsOutsidePanel()` can only **narrow** `$user->can()`: it never allows
something `can()` refuses. It resolves the user's guard with Spatie's
`Guard::getDefaultName()`, so give models that use a non-default guard a
`$guard_name` property.

Existing policies are not rewritten. Run `shield:generate` again for the
policies you want in the new style, or edit them by hand; Shield's
`--ignore-existing-policies` option skips the ones you have customized. You
can also edit the published stubs; `vendor:publish --force` restores the
package's versions.

You can call the same check from your own code, for example in a
hand-written policy:

```php
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;

public function update(User $user, Order $order): bool
{
    return $user->is($order->buyer)
        && PermissionPolicies::allowsOutsidePanel($user, 'Update:Order');
}
```

## Shield integration

`ShieldPermissionSource` builds the universe from Shield's public API:
`FilamentShield::getResources()`, `getPages()`, `getWidgets()` and
`getCustomPermissions()`. Everything Shield is configured with is honoured:
excluded resources, pages and widgets, per-resource policy methods
(`resources.manage`), key case and separator, custom permissions, tab
switches, and discovery across panels. Shield's core is never forked or
patched, and only this one class reads Shield's discovery output.

## Extending the package

**A rule class.** Implement one method, and return `Decision::Abstain` for
permissions the rule is not about:

```php
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionRule;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\Decision;

final readonly class HideSensitiveModels implements PermissionRule
{
    public function decide(PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): Decision
    {
        return is_a((string) $group->model, Sensitive::class, true) ? Decision::Deny : Decision::Abstain;
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
[How a role gets its context](#how-a-role-gets-its-context)).

## Visibility is not authorization

This package decides what a role form **presents**. It does not register a
gate, a policy or a `before` callback, and it never grants anything.

- A hidden permission still exists if Shield generated it.
- A role that already holds a hidden permission keeps it until the role is
  saved. Whether it still takes effect is up to your policies. Policies
  generated from this package's stubs refuse it outside the panel (see
  [Generated policies](#generated-policies)).
- A shown permission is only a checkbox.

The only authorization helper the package offers, `allowsOutsidePanel()`,
narrows `$user->can()` and never widens it. Keep authorization in your
policies. For example, a member role holding
`Update:User` should still be refused by a `UserPolicy` that only honours
administrators.

## Troubleshooting

**The Roles page lists no permissions, or a resource is missing.**
Shield has not generated them. Run `php artisan shield:generate --all --panel=<id>`
and check Shield's `resources.exclude`, `pages.exclude` and `widgets.exclude`
settings.

**`InvalidPolicyConfiguration` is thrown on every request.**
The config file has a typo. The message names the exact path, for example
`filament-permission-policies.guards.web.hide.resource`. Fix it, then re-run
`config:cache` if you cache the config.

**"Guard [api] is configured for roles but is not defined in config/auth.php".**
Add the guard to `config/auth.php`, or remove it from the package's config.

**"Select all" does not match the ticks after changing the guard.**
The guard field is not the package's. Use `getGuardFormComponent()`, or call
`refreshPermissionFormState()` from your field's `afterStateUpdated()` (see
[The guard field](#the-guard-field)).

**A permission is hidden on the form but a user still has it.**
Expected: hiding is not revoking (see
[Visibility is not authorization](#visibility-is-not-authorization)). Saving
the role removes permissions its context does not present.

**A user is refused on the site although their role has the permission.**
Policies generated from this package's stubs check three things outside the
panel: the user's guard is configured in `filament-permission-policies.guards`,
that guard's rules present the permission, and the user holds it. Check the
first two; a model on a non-default guard also needs a `$guard_name` property.

**`shield:generate` ignores the package's stubs.**
They must be published into `stubs/filament-shield/` in your application:
run `php artisan vendor:publish --tag="filament-permission-policies-stubs"`.

**The roles table count differs from the number of rows in the database.**
`countPresentedPermissions()` counts only what the role's context presents,
which is what the form shows.

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

See [docs/architecture.md](docs/architecture.md) for the design, the behaviour
this package was extracted from, and the review notes, and
[CHANGELOG.md](CHANGELOG.md) for what changed.

## License

MIT. See [LICENSE.md](LICENSE.md).
