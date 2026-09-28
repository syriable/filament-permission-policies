<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies;

use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\ContextResolver;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Shield\ShieldPermissionSource;

final class PermissionPoliciesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-permission-policies')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        // Rules live for the whole application. The config file's rules are
        // declared when the registry is first needed; code can add more after.
        $this->app->singleton(PolicyRegistry::class, static function (Application $app): PolicyRegistry {
            $registry = new PolicyRegistry;

            $app->make(RoleGuards::class)->applyTo($registry);

            return $registry;
        });

        // The universe is read once per request; Octane workers get a fresh one.
        $this->app->scoped(PermissionManager::class);

        $this->app->bindIf(PermissionSource::class, ShieldPermissionSource::class);
        $this->app->bindIf(ContextResolver::class, GuardContextResolver::class);
    }
}
