<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Rules;

use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionRule;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\Decision;

/**
 * Allows or denies the permissions a target matches and abstains on the rest.
 */
final readonly class TargetRule implements PermissionRule
{
    public function __construct(
        public Decision $decision,
        public Target $target,
    ) {}

    public static function allow(Target $target): self
    {
        return new self(Decision::Allow, $target);
    }

    public static function deny(Target $target): self
    {
        return new self(Decision::Deny, $target);
    }

    public function decide(PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): Decision
    {
        return $this->target->matches($permission, $group) ? $this->decision : Decision::Abstain;
    }
}
