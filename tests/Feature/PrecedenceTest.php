<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\Decision;
use Syriable\Filament\Plugins\PermissionPolicies\PolicyEvaluator;
use Syriable\Filament\Plugins\PermissionPolicies\Rules\Target;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models\Service;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\OrderResource;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\ServiceResource;

beforeEach(function (): void {
    $this->manager = useCatalog();
});

it('presents a permission no rule mentions', function (): void {
    expect($this->manager->forContext('user')->contains('Reorder:Service'))->toBeTrue();
});

it('lets a context deny what the global scope allows', function (): void {
    $this->manager->global()->allowPermissions(['Reorder:Service']);
    $this->manager->context('user')->hidePermissions(['Reorder:Service']);

    expect($this->manager->forContext('user')->contains('Reorder:Service'))->toBeFalse();
});

it('lets a context allow what the global scope denies', function (): void {
    $this->manager->global()->hideActions(['reorder']);
    $this->manager->context('admin')->allowActions(['reorder']);

    expect($this->manager->forContext('admin')->contains('Reorder:Service'))->toBeTrue()
        ->and($this->manager->forContext('user')->contains('Reorder:Service'))->toBeFalse();
});

it('falls back to the global scope when the context abstains', function (): void {
    $this->manager->global()->hideActions(['reorder']);
    $this->manager->context('user')->hidePermissions(['Delete:Order']);

    expect($this->manager->forContext('user')->contains('Reorder:Service'))->toBeFalse();
});

it('answers the brief: global allows, context denies, resource allows within the context', function (): void {
    $this->manager->global()->allowResources([ServiceResource::class]);
    $this->manager->context('user')
        ->hideActions(['forceDelete'])
        ->allowResources([ServiceResource::class]);

    // The context decides over the global scope, and inside the context a
    // Deny wins over an Allow, whatever target either rule names.
    expect($this->manager->forContext('user')->contains('ForceDelete:Service'))->toBeFalse();
});

it('lets deny win inside one scope, whatever order the rules were added in', function (array $order): void {
    $policy = $this->manager->context('user');

    foreach ($order as $rule) {
        $rule === 'deny'
            ? $policy->hideModels([Service::class])
            : $policy->allowPermissions(['View:Service']);
    }

    expect($this->manager->forContext('user')->hasGroup(ServiceResource::class))->toBeFalse();
})->with([
    'deny first' => [['deny', 'allow']],
    'allow first' => [['allow', 'deny']],
]);

it('lets deny win inside the global scope too', function (): void {
    $this->manager->global()
        ->allowPermissions(['Delete:Order'])
        ->hidePermissions(['Delete:Order']);

    expect($this->manager->forContext('anything')->contains('Delete:Order'))->toBeFalse();
});

it('re-shows a single globally hidden permission for one context', function (): void {
    $this->manager->global()->hideResources([OrderResource::class]);
    $this->manager->context('admin')->allowPermissions(['ViewAny:Order']);

    expect($this->manager->forContext('admin')->group(OrderResource::class)?->keys())->toBe(['ViewAny:Order'])
        ->and($this->manager->forContext('user')->hasGroup(OrderResource::class))->toBeFalse();
});

it('does not let an allow-list re-show what the global scope hides', function (): void {
    $this->manager->global()->hideActions(['reorder']);
    $this->manager->context('user')->onlyResources([ServiceResource::class]);

    $catalog = $this->manager->forContext('user');

    expect($catalog->groupKeys())->toContain(ServiceResource::class)
        ->and($catalog->contains('Reorder:Service'))->toBeFalse();
});

it('returns only allow or deny as a final decision', function (): void {
    $this->manager->context('user')->deny(Target::make()->actions(['delete']));

    $evaluator = app(PolicyEvaluator::class);
    $group = groupOf($this->manager->universe(), OrderResource::class);

    $decisions = array_map(
        fn (Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition $permission): Decision => $evaluator->decide($permission, $group, PermissionContext::make('user')),
        $group->permissions,
    );

    expect($decisions)->not->toContain(Decision::Abstain)
        ->and($decisions)->toContain(Decision::Deny);
});
