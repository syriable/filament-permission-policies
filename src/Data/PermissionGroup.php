<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Data;

use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;

/**
 * The permissions generated for one entity: a resource, a page, a widget,
 * or the set of custom permissions.
 */
final readonly class PermissionGroup
{
    /**
     * @param  string  $key  The entity's class name, or any unique name for custom groups.
     * @param  class-string|null  $model  The model a resource manages; null for other kinds.
     * @param  list<PermissionDefinition>  $permissions
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public GroupKind $kind,
        public string $label,
        public ?string $model = null,
        public array $permissions = [],
        public array $meta = [],
    ) {}

    /**
     * @param  list<PermissionDefinition>  $permissions
     */
    public function withPermissions(array $permissions): self
    {
        return new self($this->key, $this->kind, $this->label, $this->model, $permissions, $this->meta);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(
            static fn (PermissionDefinition $permission): string => $permission->key,
            $this->permissions,
        );
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->permissions as $permission) {
            $options[$permission->key] = $permission->label;
        }

        return $options;
    }

    public function count(): int
    {
        return count($this->permissions);
    }

    public function isEmpty(): bool
    {
        return $this->permissions === [];
    }
}
