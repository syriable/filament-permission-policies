<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Contracts;

use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\Decision;

/**
 * Decides whether one permission is presented in a context. Return
 * Decision::Abstain for permissions the rule is not about.
 */
interface PermissionRule
{
    public function decide(PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): Decision;
}
