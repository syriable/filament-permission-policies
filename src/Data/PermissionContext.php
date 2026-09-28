<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Data;

/**
 * Who the permissions are being managed for: "admin", "user", "seller", or
 * any other name. The name selects the context's rules; the attributes carry
 * whatever a custom rule needs to decide, such as the role being edited.
 */
final readonly class PermissionContext
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $name,
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function make(string $name, array $attributes = []): self
    {
        return new self($name, $attributes);
    }

    public function is(string ...$names): bool
    {
        return in_array($this->name, $names, true);
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }
}
