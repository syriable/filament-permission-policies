<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Contracts;

use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog;

/**
 * Provides the permission universe: every permission that exists, before
 * any context rule is applied. Shield is the default source.
 */
interface PermissionSource
{
    public function catalog(): PermissionCatalog;
}
