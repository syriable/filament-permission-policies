<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies;

use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\Decision;

/**
 * The one place that decides whether a permission is presented.
 *
 * Precedence, from strongest to weakest:
 *
 * 1. The context's policy. If it allows or denies, that is final.
 * 2. The global policy, when the context's policy abstains.
 * 3. The default: presented. The universe is shown unless a rule says otherwise.
 *
 * Inside one policy, Deny beats Allow (see PermissionPolicy::decide()).
 */
final readonly class PolicyEvaluator
{
    public function __construct(
        private PolicyRegistry $registry,
    ) {}

    public function decide(PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): Decision
    {
        $policies = [$this->registry->find($context->name), $this->registry->global()];

        foreach ($policies as $policy) {
            $decision = $policy?->decide($permission, $group, $context) ?? Decision::Abstain;

            if ($decision !== Decision::Abstain) {
                return $decision;
            }
        }

        return Decision::Allow;
    }

    public function isVisible(PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): bool
    {
        return $this->decide($permission, $group, $context) === Decision::Allow;
    }

    public function filter(PermissionCatalog $catalog, PermissionContext $context): PermissionCatalog
    {
        return $catalog->filter(
            fn (PermissionDefinition $permission, PermissionGroup $group): bool => $this->isVisible($permission, $group, $context),
        );
    }
}
