<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\ContextResolver;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;

/**
 * The default resolver: a role's guard names its context. A "web" role is
 * managed in the "web" context, an "admin" role in the "admin" context.
 *
 * The unsaved form state wins over the stored role, so switching the guard
 * on the form switches the permissions shown.
 */
final readonly class GuardContextResolver implements ContextResolver
{
    public function __construct(
        private Repository $config,
    ) {}

    public function resolve(array $state = [], ?Model $role = null): PermissionContext
    {
        $guard = $state['guard_name'] ?? $role?->getAttribute('guard_name') ?? $this->config->get('auth.defaults.guard');

        $guard = is_string($guard) && $guard !== '' ? $guard : 'web';

        return new PermissionContext($guard, [
            'guard' => $guard,
            'role' => $role,
            'state' => $state,
        ]);
    }
}
