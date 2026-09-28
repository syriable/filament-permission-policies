<?php

declare(strict_types=1);

arch('every file declares strict types')
    ->expect('Syriable\Filament\Plugins\PermissionPolicies')
    ->toUseStrictTypes();

arch('the rule engine knows nothing about Filament or Shield')
    ->expect([
        'Syriable\Filament\Plugins\PermissionPolicies\Contracts',
        'Syriable\Filament\Plugins\PermissionPolicies\Data',
        'Syriable\Filament\Plugins\PermissionPolicies\Enums',
        'Syriable\Filament\Plugins\PermissionPolicies\Rules',
        'Syriable\Filament\Plugins\PermissionPolicies\Sources',
        Syriable\Filament\Plugins\PermissionPolicies\PermissionPolicy::class,
        Syriable\Filament\Plugins\PermissionPolicies\PolicyRegistry::class,
        Syriable\Filament\Plugins\PermissionPolicies\PolicyEvaluator::class,
        Syriable\Filament\Plugins\PermissionPolicies\PermissionManager::class,
        Syriable\Filament\Plugins\PermissionPolicies\GuardContextResolver::class,
    ])
    ->not->toUse(['Filament', 'BezhanSalleh', 'Livewire']);

arch('only the Shield adapter and the Filament layer talk to Shield')
    ->expect('BezhanSalleh')
    ->toOnlyBeUsedIn([
        'Syriable\Filament\Plugins\PermissionPolicies\Shield',
        'Syriable\Filament\Plugins\PermissionPolicies\Filament',
        'Syriable\Filament\Plugins\PermissionPolicies\Tests',
    ]);

arch('value objects and rules are immutable')
    ->expect([
        'Syriable\Filament\Plugins\PermissionPolicies\Data',
        'Syriable\Filament\Plugins\PermissionPolicies\Rules',
    ])
    ->classes()
    ->toBeReadonly()
    ->toBeFinal();

arch('no debugging left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
