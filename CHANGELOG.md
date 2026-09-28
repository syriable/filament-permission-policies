# Changelog

All notable changes to `filament-permission-policies` are documented in this file.

## Unreleased

- Policy stubs for `shield:generate` (publish tag `filament-permission-policies-stubs`): inside the panel a permission is checked as before; outside it, `allowsOutsidePanel()` also requires the user's guard to be configured and to present the permission.
- `PermissionPolicies::allowsOutsidePanel($user, $permission)`, which only narrows `$user->can()`.
- `getGuardFormComponent()`: a guard field built from the configured guards that refreshes the form when the guard changes.
- `refreshPermissionFormState()`: fixes "select all" staying stale after a guard change; drops ticks the new context hides and fills newly shown lists from the stored role.
- Config file: role guards with label, color and hide/only/allow rules, plus global rules, validated on load.
- `RoleGuards` (`PermissionPolicies::guards()`): guard names, options, labels and colors for forms and tables.
- `allowPages()` and `allowWidgets()` on policies.
- Context-aware permission catalog on top of Filament Shield.
- Global and per-context policies with resource, model, action, permission, kind and callback rules.
- Deterministic precedence: context over global, deny over allow within a scope.
- Shield source adapter built on the public `FilamentShield` facade.
- Filament role form traits: rendering, counts and select-all from one catalog; save-time scoping.
