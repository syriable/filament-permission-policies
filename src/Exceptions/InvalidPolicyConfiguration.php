<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when config/filament-permission-policies.php names something the
 * package does not understand. A typo fails loudly instead of silently
 * showing permissions that were meant to be hidden.
 */
final class InvalidPolicyConfiguration extends InvalidArgumentException
{
    /**
     * @param  list<string>  $expected
     */
    public static function unknownKey(string $path, string $key, array $expected): self
    {
        return new self(sprintf('Unknown key [%s] in [%s]. Expected one of: %s.', $key, $path, implode(', ', $expected)));
    }

    public static function notAList(string $path): self
    {
        return new self(sprintf('[%s] must be a list of strings.', $path));
    }

    public static function unknownKind(string $path, string $kind): self
    {
        return new self(sprintf('Unknown kind [%s] in [%s]. Expected one of: resource, page, widget, custom.', $kind, $path));
    }

    public static function unknownClass(string $path, string $class): self
    {
        return new self(sprintf('Class [%s] in [%s] does not exist.', $class, $path));
    }

    public static function unknownGuard(string $guard): self
    {
        return new self(sprintf('Guard [%s] is configured for roles but is not defined in config/auth.php.', $guard));
    }
}
