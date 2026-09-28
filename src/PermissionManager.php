<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies;

use Illuminate\Database\Eloquent\Model;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\ContextResolver;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;

/**
 * Answers "which permissions are managed in this context?".
 *
 * The universe is read from the source once per instance; the container
 * scopes the manager to a request, so it is never stale across requests.
 */
final class PermissionManager
{
    private ?PermissionCatalog $universe = null;

    public function __construct(
        private readonly PermissionSource $source,
        private readonly PolicyRegistry $registry,
        private readonly PolicyEvaluator $evaluator,
        private readonly ContextResolver $resolver,
        private readonly RoleGuards $guards,
    ) {}

    /**
     * Every permission that exists, before any rule is applied.
     */
    public function universe(): PermissionCatalog
    {
        return $this->universe ??= $this->source->catalog();
    }

    /**
     * The permissions presented in the context.
     */
    public function forContext(PermissionContext|string $context): PermissionCatalog
    {
        $context = is_string($context) ? PermissionContext::make($context) : $context;

        return $this->evaluator->filter($this->universe(), $context);
    }

    /**
     * The permissions presented for a role, stored or being edited.
     *
     * @param  array<string, mixed>  $state
     */
    public function forRole(?Model $role, array $state = []): PermissionCatalog
    {
        return $this->forContext($this->resolveContext($state, $role));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function resolveContext(array $state = [], ?Model $role = null): PermissionContext
    {
        return $this->resolver->resolve($state, $role);
    }

    public function global(): PermissionPolicy
    {
        return $this->registry->global();
    }

    public function context(string $name): PermissionPolicy
    {
        return $this->registry->context($name);
    }

    /**
     * The guards roles can be created for, with their labels and colors.
     */
    public function guards(): RoleGuards
    {
        return $this->guards;
    }

    public function registry(): PolicyRegistry
    {
        return $this->registry;
    }
}
