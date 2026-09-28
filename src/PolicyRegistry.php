<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies;

/**
 * Holds the global policy and one policy per named context. Policies are
 * created on first use, so declaring rules for a new context is all it takes
 * to add one.
 */
final class PolicyRegistry
{
    private readonly PermissionPolicy $global;

    /**
     * @var array<string, PermissionPolicy>
     */
    private array $contexts = [];

    public function __construct()
    {
        $this->global = new PermissionPolicy;
    }

    public function global(): PermissionPolicy
    {
        return $this->global;
    }

    public function context(string $name): PermissionPolicy
    {
        return $this->contexts[$name] ??= new PermissionPolicy($name);
    }

    public function find(string $name): ?PermissionPolicy
    {
        return $this->contexts[$name] ?? null;
    }

    public function hasContext(string $name): bool
    {
        return isset($this->contexts[$name]);
    }

    /**
     * @return list<string>
     */
    public function contexts(): array
    {
        return array_keys($this->contexts);
    }
}
