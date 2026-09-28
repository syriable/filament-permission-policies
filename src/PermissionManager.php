<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Guard;
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

    /**
     * Catalogs of contexts looked up by name, which carry no attributes and
     * therefore always filter the same way within a request.
     *
     * @var array<string, PermissionCatalog>
     */
    private array $namedCatalogs = [];

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
        if (is_string($context)) {
            return $this->namedCatalogs[$context] ??= $this->evaluator->filter($this->universe(), PermissionContext::make($context));
        }

        return $this->evaluator->filter($this->universe(), $context);
    }

    /**
     * Whether a permission counts for a user outside the Filament panel: on
     * the site, in an API, in a job. The generated policies call this when
     * Filament is not serving a request.
     *
     * It only ever narrows $user->can(). The permission counts when the
     * user's guard is one roles are configured for, that guard's role form
     * presents the permission, and the user holds it. A permission Shield does
     * not know about is not narrowed beyond the guard check.
     */
    public function allowsOutsidePanel(Model&Authorizable $user, string $permission): bool
    {
        $guard = Guard::getDefaultName($user);

        if (! $this->guards->has($guard)) {
            return false;
        }

        // The guard's context by name: there is no role here, only the
        // question of whether this guard's role form could grant it.
        $context = $this->resolveContext(['guard_name' => $guard])->name;

        if ($this->universe()->contains($permission) && ! $this->forContext($context)->contains($permission)) {
            return false;
        }

        return $user->can($permission);
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
