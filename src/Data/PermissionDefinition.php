<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Data;

/**
 * One permission as it is stored (key), the ability it stands for (action)
 * and how it is shown (label).
 *
 * The action is the policy method a resource permission was generated from,
 * such as "forceDelete", or the prefix of a page or widget permission. Custom
 * permissions have no action.
 */
final readonly class PermissionDefinition
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public ?string $action,
        public string $label,
        public array $meta = [],
    ) {}
}
