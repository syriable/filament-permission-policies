<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Shield;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\Str;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;

/**
 * Reads the permission universe from Shield.
 *
 * Shield keeps discovering entities and building permission keys; this class
 * only translates its public output (the FilamentShield facade) into
 * permission groups. It is the only place in the package that knows the shape
 * of that output.
 */
final readonly class ShieldPermissionSource implements PermissionSource
{
    public const string CUSTOM_GROUP = 'custom';

    public function catalog(): PermissionCatalog
    {
        return new PermissionCatalog([
            ...$this->resources(),
            ...$this->entities(FilamentShield::getPages() ?? [], GroupKind::Page, 'pageFqcn', config('filament-shield.pages.prefix')),
            ...$this->entities(FilamentShield::getWidgets() ?? [], GroupKind::Widget, 'widgetFqcn', config('filament-shield.widgets.prefix')),
            $this->custom(),
        ]);
    }

    /**
     * @return list<PermissionGroup>
     */
    private function resources(): array
    {
        $groups = [];

        /** @var array{resourceFqcn: class-string, modelFqcn: class-string, permissions: array<string, array{key: string, label: string}>} $resource */
        foreach (FilamentShield::getResources() ?? [] as $resource) {
            $permissions = [];

            foreach ($resource['permissions'] as $action => $permission) {
                $permissions[] = new PermissionDefinition($permission['key'], (string) $action, $permission['label']);
            }

            $groups[] = new PermissionGroup(
                key: $resource['resourceFqcn'],
                kind: GroupKind::Resource,
                label: FilamentShield::getLocalizedResourceLabel($resource['resourceFqcn']),
                model: $resource['modelFqcn'],
                permissions: $permissions,
            );
        }

        return $groups;
    }

    /**
     * Pages and widgets: one permission each, named by the configured prefix.
     *
     * @param  array<array-key, mixed>  $entities
     * @return list<PermissionGroup>
     */
    private function entities(array $entities, GroupKind $kind, string $classKey, mixed $prefix): array
    {
        $groups = [];

        /** @var array<string, mixed> $entity */
        foreach ($entities as $entity) {
            /** @var class-string $class */
            $class = $entity[$classKey];

            /** @var array<string, string> $entityPermissions */
            $entityPermissions = $entity['permissions'] ?? [];
            $permissions = [];

            foreach ($entityPermissions as $key => $label) {
                $permissions[] = new PermissionDefinition($key, is_string($prefix) ? $prefix : null, $label);
            }

            $groups[] = new PermissionGroup(
                key: $class,
                kind: $kind,
                label: Str::headline(class_basename($class)),
                permissions: $permissions,
            );
        }

        return $groups;
    }

    private function custom(): PermissionGroup
    {
        $permissions = [];

        foreach (FilamentShield::getCustomPermissions(localized: true) ?? [] as $key => $label) {
            $permissions[] = new PermissionDefinition((string) $key, null, (string) $label);
        }

        return new PermissionGroup(
            key: self::CUSTOM_GROUP,
            kind: GroupKind::Custom,
            label: Str::headline(self::CUSTOM_GROUP),
            permissions: $permissions,
        );
    }
}
