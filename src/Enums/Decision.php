<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Enums;

/**
 * What a rule says about one permission. Abstain means the rule has no
 * opinion, so a broader scope (or the default) decides.
 */
enum Decision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case Abstain = 'abstain';
}
