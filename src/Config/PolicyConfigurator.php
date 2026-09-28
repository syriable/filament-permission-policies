<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Config;

use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\Exceptions\InvalidPolicyConfiguration;
use Syriable\Filament\Plugins\PermissionPolicies\PermissionPolicy;

/**
 * Turns one rule set from the config file (its "hide", "only" and "allow"
 * sections) into rules on a policy. The config is validated as it is read:
 * an unknown section or rule type throws instead of being ignored.
 */
final readonly class PolicyConfigurator
{
    /**
     * @var array<string, list<string>>
     */
    private const array TYPES = [
        'hide' => ['resources', 'pages', 'widgets', 'models', 'actions', 'permissions', 'kinds'],
        'only' => ['resources', 'models', 'actions', 'permissions'],
        'allow' => ['resources', 'pages', 'widgets', 'models', 'actions', 'permissions'],
    ];

    /**
     * Rule types whose values are class names, checked to exist.
     *
     * @var list<string>
     */
    private const array CLASS_TYPES = ['resources', 'pages', 'widgets', 'models'];

    /**
     * @param  array<array-key, mixed>  $rules  A guard's or the global section.
     * @param  list<string>  $ignore  Keys that belong to the caller, such as "label".
     */
    public function apply(PermissionPolicy $policy, array $rules, string $path, array $ignore = []): void
    {
        foreach ($rules as $section => $types) {
            if (in_array($section, $ignore, true)) {
                continue;
            }

            if (! is_string($section) || ! isset(self::TYPES[$section])) {
                throw InvalidPolicyConfiguration::unknownKey($path, (string) $section, [...array_keys(self::TYPES), ...$ignore]);
            }

            if (! is_array($types)) {
                throw InvalidPolicyConfiguration::notAList("{$path}.{$section}");
            }

            foreach ($types as $type => $values) {
                if (! is_string($type) || ! in_array($type, self::TYPES[$section], true)) {
                    throw InvalidPolicyConfiguration::unknownKey("{$path}.{$section}", (string) $type, self::TYPES[$section]);
                }

                $values = $this->strings($values, "{$path}.{$section}.{$type}");

                // An empty list means "not configured", never "hide everything".
                if ($values === []) {
                    continue;
                }

                $this->applyRule($policy, $section, $type, $values, "{$path}.{$section}.{$type}");
            }
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function applyRule(PermissionPolicy $policy, string $section, string $type, array $values, string $path): void
    {
        if (in_array($type, self::CLASS_TYPES, true)) {
            $this->applyClassRule($policy, "{$section}.{$type}", $this->classes($values, $path));

            return;
        }

        match ("{$section}.{$type}") {
            'hide.actions' => $policy->hideActions($values),
            'hide.permissions' => $policy->hidePermissions($values),
            'hide.kinds' => $policy->hideKinds($this->kinds($values, $path)),
            'only.actions' => $policy->onlyActions($values),
            'only.permissions' => $policy->onlyPermissions($values),
            'allow.actions' => $policy->allowActions($values),
            'allow.permissions' => $policy->allowPermissions($values),
            default => throw InvalidPolicyConfiguration::unknownKey($path, $type, self::TYPES[$section]),
        };
    }

    /**
     * @param  list<class-string>  $classes
     */
    private function applyClassRule(PermissionPolicy $policy, string $rule, array $classes): void
    {
        match ($rule) {
            'hide.resources' => $policy->hideResources($classes),
            'hide.pages' => $policy->hidePages($classes),
            'hide.widgets' => $policy->hideWidgets($classes),
            'hide.models' => $policy->hideModels($classes),
            'only.resources' => $policy->onlyResources($classes),
            'only.models' => $policy->onlyModels($classes),
            'allow.resources' => $policy->allowResources($classes),
            'allow.pages' => $policy->allowPages($classes),
            'allow.widgets' => $policy->allowWidgets($classes),
            'allow.models' => $policy->allowModels($classes),
            default => throw InvalidPolicyConfiguration::unknownKey($rule, $rule, self::TYPES['hide']),
        };
    }

    /**
     * A misspelled class would otherwise hide nothing and go unnoticed.
     *
     * @param  list<string>  $values
     * @return list<class-string>
     */
    private function classes(array $values, string $path): array
    {
        $classes = [];

        foreach ($values as $value) {
            if (! class_exists($value) && ! interface_exists($value)) {
                throw InvalidPolicyConfiguration::unknownClass($path, $value);
            }

            $classes[] = $value;
        }

        return $classes;
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values, string $path): array
    {
        if (! is_array($values) || ! array_is_list($values)) {
            throw InvalidPolicyConfiguration::notAList($path);
        }

        $strings = [];

        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw InvalidPolicyConfiguration::notAList($path);
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $values
     * @return list<GroupKind>
     */
    private function kinds(array $values, string $path): array
    {
        return array_map(
            static fn (string $kind): GroupKind => GroupKind::tryFrom($kind) ?? throw InvalidPolicyConfiguration::unknownKind($path, $kind),
            $values,
        );
    }
}
