<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Rules;

use Illuminate\Support\Str;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;

/**
 * Describes which permissions a rule is about.
 *
 * Every dimension that is set must match (AND); a dimension matches when any
 * of its values does (OR). A target with nothing set matches every permission.
 *
 *     Target::make()->groups([ServiceResource::class])->actions(['delete'])
 *
 * matches only the "delete" permission of the service resource.
 */
final readonly class Target
{
    /**
     * @param  list<GroupKind>|null  $kinds
     * @param  list<string>|null  $groups
     * @param  list<string>|null  $models
     * @param  list<string>|null  $actions  Normalized to camelCase.
     * @param  list<string>|null  $permissions
     */
    private function __construct(
        public ?array $kinds = null,
        public ?array $groups = null,
        public ?array $models = null,
        public ?array $actions = null,
        public ?array $permissions = null,
    ) {}

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  list<GroupKind>  $kinds
     */
    public function kinds(array $kinds): self
    {
        return new self($kinds, $this->groups, $this->models, $this->actions, $this->permissions);
    }

    /**
     * Resource, page or widget classes, or custom group keys.
     *
     * @param  list<string>  $groups
     */
    public function groups(array $groups): self
    {
        return new self($this->kinds, $groups, $this->models, $this->actions, $this->permissions);
    }

    /**
     * Model classes. Matches every resource that manages one of them.
     *
     * @param  list<string>  $models
     */
    public function models(array $models): self
    {
        return new self($this->kinds, $this->groups, $models, $this->actions, $this->permissions);
    }

    /**
     * Policy methods or prefixes, such as "forceDelete" or "reorder". Any
     * case is accepted: "ForceDelete" and "force_delete" match too.
     *
     * @param  list<string>  $actions
     */
    public function actions(array $actions): self
    {
        return new self(
            $this->kinds,
            $this->groups,
            $this->models,
            array_map($this->normalizeAction(...), $actions),
            $this->permissions,
        );
    }

    /**
     * Exact permission keys, such as "ForceDelete:User".
     *
     * @param  list<string>  $permissions
     */
    public function permissions(array $permissions): self
    {
        return new self($this->kinds, $this->groups, $this->models, $this->actions, $permissions);
    }

    public function matches(PermissionDefinition $permission, PermissionGroup $group): bool
    {
        if ($this->kinds !== null && ! in_array($group->kind, $this->kinds, true)) {
            return false;
        }

        if ($this->groups !== null && ! in_array($group->key, $this->groups, true)) {
            return false;
        }

        if ($this->models !== null && ($group->model === null || ! in_array($group->model, $this->models, true))) {
            return false;
        }

        if ($this->actions !== null && ($permission->action === null || ! in_array($this->normalizeAction($permission->action), $this->actions, true))) {
            return false;
        }

        return $this->permissions === null || in_array($permission->key, $this->permissions, true);
    }

    private function normalizeAction(string $action): string
    {
        return Str::camel($action);
    }
}
