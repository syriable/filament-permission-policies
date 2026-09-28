<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Rules;

use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionRule;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\Decision;

/**
 * An allow-list: within its scope, denies every permission the target does
 * not match.
 *
 * Matching permissions get Abstain, not Allow. "Only these resources" narrows
 * what a context shows; it must not bring back a permission another rule
 * hides, which an Allow would do across scopes.
 */
final readonly class OnlyRule implements PermissionRule
{
    public function __construct(
        public Target $target,
        public Target $scope,
    ) {}

    public function decide(PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): Decision
    {
        if (! $this->scope->matches($permission, $group)) {
            return Decision::Abstain;
        }

        return $this->target->matches($permission, $group) ? Decision::Abstain : Decision::Deny;
    }
}
