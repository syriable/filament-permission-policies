# Architecture

This document records why the package exists, what it was extracted from, and
how it is built. The README explains how to use it.

## 1. Where it came from

The package was extracted from the Fluxwork application (`modules/identity`).
Fluxwork manages two kinds of roles with Filament Shield:

- **administrator roles** on the `admin` guard, for the Filament panel;
- **member roles** on the `web` guard, for the public site.

Both kinds are edited on the same Shield role form. The prototype customized
that form in `Modules\Identity\Filament\Resources\Roles\RoleResource`.

### Current behaviour (prototype)

| # | Behaviour | Where | Concern |
|---|---|---|---|
| B1 | A member role never sees the role resource | `MEMBER_HIDDEN_RESOURCES`, section `->hidden()` | resource visibility, contextual |
| B2 | A member role never sees `Restore`, `ForceDelete`, `ForceDeleteAny`, `RestoreAny`, `Replicate`, `Reorder` | `MEMBER_HIDDEN_PERMISSIONS`, checkbox `->options()` filter | permission visibility, contextual |
| B3 | The resources tab badge counts only what a member role can see | `getResourceTabBadgeCountNew()` | counting |
| B4 | An administrator role sees everything Shield generates | absence of rules for `admin` | context default |
| B5 | Switching the guard on the form switches what is shown | `$get('guard_name')` in closures, `->live()` guard select | context resolution |
| B6 | Pages, widgets and custom permissions are not filtered | only the resources tab is overridden | scope of rules |
| B7 | The super admin role cannot be edited or deleted | `getAuthorizationResponse()`, `RolePolicy` | **authorization**, not presentation |
| B8 | The super admin role is never assignable | `ListAssignableRolesAction` | role assignment, not presentation |
| B9 | A member holding a `*:User` permission gains nothing | `UserPolicy::administratorCan()` | **authorization** |

B7 to B9 confirm that the application keeps authorization in policies. The
presentation rules (B1 to B6) never grant anything. That separation is kept.

### Problems in the prototype

1. **The rules live in the UI.** Constants and `if ($get('guard_name') == 'web')`
   checks sit inside a Filament resource, in three separate closures.
2. **The filtering is duplicated.** The badge, the sections and the checkbox
   options each re-implement the filter, and they parse keys differently:
   `Str::beforeLast($key, ':')` in the badge, `Str::before(':')` in the options.
   They agree only because every key has one separator.
3. **Rules depend on Shield's key format.** Hiding "ForceDelete" works by
   reading the key prefix, so changing Shield's `permissions.case` or
   `separator` silently disables the rule.
4. **Hidden is not removed.** The role resource section is built and then
   `hidden()`. Its checkbox list, hydration and select-all wiring still exist.
5. **Hidden permissions can survive a save.** Nothing on the server side limits
   the saved permissions to what the context shows. A role moved from `admin`
   to `web` keeps its administrator permissions unless the form happens not to
   submit them.
6. **Contexts are hard-coded.** "Member" means `guard_name == 'web'`. A third
   context (seller, moderator) means editing every closure again.
7. **Other kinds are not covered.** The same rule for pages or widgets would
   need yet another override.

### Extracted business rules

- **R1.** Shield defines the permission universe. Nothing in this layer
  creates, renames or deletes a permission.
- **R2.** A context decides which part of the universe a role form presents.
- **R3.** A role's context follows from the role (by default, its guard).
- **R4.** With no rule, a context presents the whole universe (B4).
- **R5.** A rule may target a resource, a model, an ability (action), a single
  permission, an entity kind, or anything a callback can decide.
- **R6.** Whatever is not presented does not exist for the form: no section,
  no option, no count, no select-all entry, and no persisted grant.
- **R7.** Every count is derived from the presented structure.
- **R8.** Presentation never affects authorization.

## 2. Design

### Layers

```
Filament   HasPermissionPolicies, ScopesPermissionsOnCreate/OnSave
   │       render and persist one catalog; no rules here
   ▼
Core       PermissionManager ─► PolicyEvaluator ─► PolicyRegistry ─► PermissionPolicy ─► rules
   │       contexts, rules, precedence, filtering, counting
   ▼
Source     PermissionSource (contract) ◄── ShieldPermissionSource (adapter)
   │
   ▼
Shield ─► Spatie Laravel Permission ─► database
```

The core has no Filament, Livewire or Shield imports; an architecture test
enforces that. Only `Shield\ShieldPermissionSource` reads Shield's discovery
output, and it uses the public `FilamentShield` facade and Shield's config.
The Filament layer uses Shield's public role-resource extension points (the
static form methods on a published `RoleResource`, and the `$permissions`
property of its create and edit pages).

### Data flow

```
ShieldPermissionSource::catalog()        universe (PermissionCatalog)
        │
ContextResolver::resolve(state, role)    PermissionContext
        │
PolicyEvaluator::filter(universe, ctx)   presented catalog (PermissionCatalog)
        │
        ├── tabs, sections, checkbox options, badges, select-all
        ├── selectedCount() / permissionCount() / groupCount()
        └── intersect() before Shield syncs the role's permissions
```

There is one filtering function and one presented structure per request and
role state. The UI never filters.

### Classes

| Class | Responsibility |
|---|---|
| `Data\PermissionDefinition` | One permission: key, action, label. |
| `Data\PermissionGroup` | The permissions of one entity (resource, page, widget, custom). |
| `Data\PermissionCatalog` | Immutable ordered groups; filtering, lookup, counts, intersect. Never holds an empty group. |
| `Data\PermissionContext` | A context name plus attributes for custom rules. |
| `Enums\Decision` | Allow, Deny, Abstain. |
| `Enums\GroupKind` | Resource, Page, Widget, Custom. |
| `Contracts\PermissionRule` | Decides one permission in one context. |
| `Contracts\PermissionSource` | Provides the universe. |
| `Contracts\ContextResolver` | Maps a role (stored or on a form) to a context. |
| `Rules\Target` | Declarative matcher: kinds, groups, models, actions, keys. |
| `Rules\TargetRule` | Allow or deny what a target matches. |
| `Rules\OnlyRule` | Allow-list: deny what a target does not match, within a scope. |
| `Rules\CallbackRule` | A rule written as a closure. |
| `PermissionPolicy` | The rules of one scope, with a fluent API; combines rules deny-wins. |
| `PolicyRegistry` | The global policy and one policy per context. |
| `PolicyEvaluator` | Precedence between scopes; filters a catalog. |
| `PermissionManager` | The facade target: universe, forContext, forRole, context(), global(). |
| `GuardContextResolver` | Default resolver: context name = role guard. |
| `Sources\InMemoryPermissionSource` | A fixed universe for tests or non-Shield permissions. |
| `Shield\ShieldPermissionSource` | Translates Shield's output into a catalog. |
| `Filament\Concerns\HasPermissionPolicies` | Renders Shield's role form from the catalog. |
| `Filament\Concerns\ScopesPermissionsOnCreate` / `OnSave` | Limit persisted permissions to the catalog. |

### Precedence

1. The context's policy. If it returns Allow or Deny, that is final.
2. The global policy, when the context abstains.
3. Default: presented.

Inside one policy, any Deny wins over any Allow, so the order rules are added
in never matters. "Only" rules abstain on what they match, so an allow-list
narrows a context without re-showing something the global scope hides.

This answers the brief's question — global allows, context denies, resource
allows inside the context — with **hidden**: the context outranks the global
scope, and inside the context Deny outranks Allow.

Targets (resource, model, action, permission) are not precedence tiers. A
model is not more specific than an action or the reverse, so ranking them
would be arbitrary. To express "hide delete everywhere except on services",
target the exception directly (`Target::make()->groups([...])->actions([...])`)
or use `hideWhen()`.

### Extension points

- Add a context: declare its rules. Nothing else changes.
- Add a rule type: implement `PermissionRule`, or pass a closure.
- Change how a role maps to a context: bind your own `ContextResolver`.
- Add permissions that do not come from Shield: bind a `PermissionSource`
  that merges `ShieldPermissionSource` with your own catalog.
- Change how a group renders: override `getPermissionGroupSection()` or
  `getPermissionCheckboxList()` on your role resource.

### Deliberately out of scope

- **Authorization.** Policies and the gate stay authoritative. A hidden
  permission is never granted by this package, and a shown one is only a
  checkbox.
- **Role visibility in role pickers** (B8). Which roles a user may assign is an
  assignment rule of the application, not a property of the permission
  universe.
- **Creating permissions.** Shield generates them; Shield's pages create the
  permission rows they sync.

## 3. Review notes

A second pass looked for the issues the brief lists.

- **Duplicated logic:** one filter (`PolicyEvaluator::filter`); the checkbox
  lists, tabs, badges and select-all all read `getPermissionCheckboxListOptions`
  or the catalog. Tab enablement and tab state names each exist once.
- **Shield internal API:** the source uses `FilamentShield` facade methods and
  config keys. The Filament layer overrides `getShieldFormComponents()` and
  `getSelectAllFormComponent()`, which Shield documents as the customization
  path of a published role resource, and uses Shield's `Utils` tab flags and
  plugin column getters.
- **Save ordering:** `EditRecord::save()` calls `beforeSave` before
  `mutateFormDataBeforeSave`, where Shield fills `$permissions`. The traits
  therefore extend `mutateFormDataBefore*` rather than the hooks.
- **Counts:** every count is a method of the presented catalog; a test asserts
  that the checkbox options add up to the badge counts in each context.
- **Authorization assumptions:** none. The package never registers a gate or
  policy.
