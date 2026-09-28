<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Data;

use Closure;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;

/**
 * An immutable, ordered set of permission groups.
 *
 * The same type holds the permission universe and the structure filtered for
 * a context, so every count the UI shows is derived from the one catalog it
 * renders. A catalog never holds an empty group.
 */
final readonly class PermissionCatalog
{
    /**
     * @var list<PermissionGroup>
     */
    public array $groups;

    /**
     * @param  iterable<PermissionGroup>  $groups
     */
    public function __construct(iterable $groups = [])
    {
        $kept = [];

        foreach ($groups as $group) {
            if (! $group->isEmpty()) {
                $kept[] = $group;
            }
        }

        $this->groups = $kept;
    }

    /**
     * Keeps the permissions the callback accepts and drops groups left empty.
     *
     * @param  Closure(PermissionDefinition, PermissionGroup): bool  $callback
     */
    public function filter(Closure $callback): self
    {
        $groups = [];

        foreach ($this->groups as $group) {
            $groups[] = $group->withPermissions(array_values(array_filter(
                $group->permissions,
                static fn (PermissionDefinition $permission): bool => $callback($permission, $group),
            )));
        }

        return new self($groups);
    }

    /**
     * Appends the other catalog's groups. A group whose key already exists
     * is ignored, so the first source of a group wins.
     */
    public function merge(self $other): self
    {
        $known = array_flip($this->groupKeys());

        return new self([
            ...$this->groups,
            ...array_filter($other->groups, static fn (PermissionGroup $group): bool => ! isset($known[$group->key])),
        ]);
    }

    /**
     * @return list<PermissionGroup>
     */
    public function groups(?GroupKind $kind = null): array
    {
        if (! $kind instanceof GroupKind) {
            return $this->groups;
        }

        return array_values(array_filter(
            $this->groups,
            static fn (PermissionGroup $group): bool => $group->kind === $kind,
        ));
    }

    public function group(string $key): ?PermissionGroup
    {
        foreach ($this->groups as $group) {
            if ($group->key === $key) {
                return $group;
            }
        }

        return null;
    }

    public function hasGroup(string $key): bool
    {
        return $this->group($key) instanceof PermissionGroup;
    }

    /**
     * @return list<string>
     */
    public function groupKeys(?GroupKind $kind = null): array
    {
        return array_map(static fn (PermissionGroup $group): string => $group->key, $this->groups($kind));
    }

    /**
     * The models behind the resource groups, each listed once.
     *
     * @return list<class-string>
     */
    public function models(): array
    {
        $models = [];

        foreach ($this->groups(GroupKind::Resource) as $group) {
            if ($group->model !== null) {
                $models[$group->model] = true;
            }
        }

        return array_keys($models);
    }

    /**
     * @return list<PermissionDefinition>
     */
    public function permissions(?GroupKind $kind = null): array
    {
        $permissions = [];

        foreach ($this->groups($kind) as $group) {
            array_push($permissions, ...$group->permissions);
        }

        return $permissions;
    }

    /**
     * @return list<string>
     */
    public function keys(?GroupKind $kind = null): array
    {
        return array_values(array_unique(array_map(
            static fn (PermissionDefinition $permission): string => $permission->key,
            $this->permissions($kind),
        )));
    }

    /**
     * @return array<string, string>
     */
    public function options(?GroupKind $kind = null): array
    {
        $options = [];

        foreach ($this->groups($kind) as $group) {
            $options += $group->options();
        }

        return $options;
    }

    public function contains(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /**
     * The given keys that exist in this catalog, in catalog order. Use it to
     * drop anything a context does not manage before it is persisted.
     *
     * @param  iterable<mixed>  $keys
     * @return list<string>
     */
    public function intersect(iterable $keys, ?GroupKind $kind = null): array
    {
        $given = [];

        foreach ($keys as $key) {
            if (is_string($key)) {
                $given[$key] = true;
            }
        }

        return array_values(array_filter(
            $this->keys($kind),
            static fn (string $key): bool => isset($given[$key]),
        ));
    }

    public function permissionCount(?GroupKind $kind = null): int
    {
        return count($this->keys($kind));
    }

    public function groupCount(?GroupKind $kind = null): int
    {
        return count($this->groups($kind));
    }

    /**
     * How many of the given keys this catalog presents. Keys outside the
     * catalog are not counted, so "selected" never exceeds "available".
     *
     * @param  iterable<mixed>  $keys
     */
    public function selectedCount(iterable $keys, ?GroupKind $kind = null): int
    {
        return count($this->intersect($keys, $kind));
    }

    public function isEmpty(): bool
    {
        return $this->groups === [];
    }
}
