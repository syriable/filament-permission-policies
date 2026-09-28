<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;
use Syriable\Filament\Plugins\PermissionPolicies\Config\PolicyConfigurator;
use Syriable\Filament\Plugins\PermissionPolicies\Exceptions\InvalidPolicyConfiguration;

/**
 * The guards roles can be created for, as config/filament-permission-policies.php
 * lists them, and the rules each one's role form follows.
 *
 * Each guard is a context: its "hide", "only" and "allow" sections become the
 * rules of the context with the guard's name, and the "global" section becomes
 * the global rules. Forms and tables read labels, colors and options here, so
 * adding a guard is a config change only.
 */
final readonly class RoleGuards
{
    private const string CONFIG = 'filament-permission-policies';

    /**
     * Keys of a guard entry that describe the guard rather than its rules.
     *
     * @var list<string>
     */
    private const array DESCRIPTION_KEYS = ['label', 'color'];

    public function __construct(
        private Repository $config,
        private Translator $translator,
        private PolicyConfigurator $configurator,
    ) {}

    /**
     * The configured guards in order, or every auth guard when none are.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $configured = array_keys($this->configured());

        if ($configured !== []) {
            return array_map(strval(...), $configured);
        }

        return array_map(strval(...), array_keys($this->authGuards()));
    }

    public function has(string $guard): bool
    {
        return in_array($guard, $this->names(), true);
    }

    /**
     * Guard name => label, for a select field or a table filter.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->names() as $guard) {
            $options[$guard] = $this->label($guard);
        }

        return $options;
    }

    public function label(string $guard): string
    {
        $label = $this->entry($guard)['label'] ?? null;

        if (! is_string($label) || $label === '') {
            return Str::headline($guard);
        }

        if ($this->translator->has($label)) {
            $translated = $this->translator->get($label);

            return is_string($translated) ? $translated : $label;
        }

        return $label;
    }

    public function color(string $guard): string
    {
        $color = $this->entry($guard)['color'] ?? null;

        return is_string($color) && $color !== '' ? $color : 'gray';
    }

    /**
     * Declares the configured rules on the registry: the global section on
     * the global policy, each guard's sections on the context of its name.
     */
    public function applyTo(PolicyRegistry $registry): void
    {
        $global = $this->config->get(self::CONFIG.'.global', []);

        if (is_array($global)) {
            $this->configurator->apply($registry->global(), $global, self::CONFIG.'.global');
        }

        $authGuards = $this->authGuards();

        foreach ($this->configured() as $guard => $entry) {
            $guard = (string) $guard;

            if (! array_key_exists($guard, $authGuards)) {
                throw InvalidPolicyConfiguration::unknownGuard($guard);
            }

            $this->configurator->apply(
                $registry->context($guard),
                is_array($entry) ? $entry : [],
                self::CONFIG.".guards.{$guard}",
                self::DESCRIPTION_KEYS,
            );
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function configured(): array
    {
        $guards = $this->config->get(self::CONFIG.'.guards', []);

        return is_array($guards) ? $guards : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function entry(string $guard): array
    {
        $entry = $this->configured()[$guard] ?? [];

        return is_array($entry) ? $entry : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function authGuards(): array
    {
        $guards = $this->config->get('auth.guards', []);

        return is_array($guards) ? $guards : [];
    }
}
