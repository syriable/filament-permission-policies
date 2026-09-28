<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\ContextResolver;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionSource;
use Syriable\Filament\Plugins\PermissionPolicies\Shield\ShieldPermissionSource;

final class PermissionPoliciesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('filament-permission-policies');
    }

    public function packageRegistered(): void
    {
        // Rules are declared once at boot and live for the whole application.
        $this->app->singleton(PolicyRegistry::class);

        // The universe is read once per request; Octane workers get a fresh one.
        $this->app->scoped(PermissionManager::class);

        $this->app->bindIf(PermissionSource::class, ShieldPermissionSource::class);
        $this->app->bindIf(ContextResolver::class, GuardContextResolver::class);
    }
}
