# Changelog

All notable changes to `filament-permission-policies` are documented in this file.

## Unreleased

- Config file: role guards with label, color and hide/only/allow rules, plus global rules, validated on load.
- `RoleGuards` (`PermissionPolicies::guards()`): guard names, options, labels and colors for forms and tables.
- `allowPages()` and `allowWidgets()` on policies.
- Context-aware permission catalog on top of Filament Shield.
- Global and per-context policies with resource, model, action, permission, kind and callback rules.
- Deterministic precedence: context over global, deny over allow within a scope.
- Shield source adapter built on the public `FilamentShield` facade.
- Filament role form traits: rendering, counts and select-all from one catalog; save-time scoping.
