<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Rules;

use Closure;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionRule;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\Decision;

/**
 * A rule written as a closure, for conditions the declarative rules cannot
 * express, such as ones that read the context's attributes.
 */
final readonly class CallbackRule implements PermissionRule
{
    /**
     * @param  Closure(PermissionDefinition, PermissionGroup, PermissionContext): Decision  $callback
     */
    public function __construct(
        private Closure $callback,
    ) {}

    /**
     * Denies the permissions for which the condition returns true.
     *
     * @param  Closure(PermissionDefinition, PermissionGroup, PermissionContext): bool  $condition
     */
    public static function denyWhen(Closure $condition): self
    {
        return new self(
            static fn (PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): Decision => $condition($permission, $group, $context) ? Decision::Deny : Decision::Abstain,
        );
    }

    public function decide(PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): Decision
    {
        return ($this->callback)($permission, $group, $context);
    }
}
