<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Sources;

use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog;

/**
 * A fixed universe. Useful for testing rules without Shield, and for
 * permissions that do not come from Filament entities.
 */
final readonly class InMemoryPermissionSource implements PermissionSource
{
    public function __construct(
        private PermissionCatalog $catalog,
    ) {}

    public function catalog(): PermissionCatalog
    {
        return $this->catalog;
    }
}
