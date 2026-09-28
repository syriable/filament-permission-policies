<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies;

use Closure;
use Syriable\Filament\Plugins\PermissionPolicies\Contracts\PermissionRule;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionContext;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionDefinition;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\Decision;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\Rules\CallbackRule;
use Syriable\Filament\Plugins\PermissionPolicies\Rules\OnlyRule;
use Syriable\Filament\Plugins\PermissionPolicies\Rules\Target;
use Syriable\Filament\Plugins\PermissionPolicies\Rules\TargetRule;

/**
 * The rules of one scope: the global scope or a named context.
 *
 * Within a policy a Deny from any rule wins over an Allow from any other, so
 * the order rules are added in never changes the outcome. Action rules
 * (hideActions, allowActions, onlyActions) apply to resource permissions only;
 * pages and widgets are handled with hidePages, hideWidgets or hideKinds.
 */
final class PermissionPolicy
{
    /**
     * @var list<PermissionRule>
     */
    private array $rules = [];

    public function __construct(
        public readonly ?string $context = null,
    ) {}

    /**
     * @param  PermissionRule|Closure(PermissionDefinition, PermissionGroup, PermissionContext): Decision  $rule
     */
    public function rule(PermissionRule|Closure $rule): self
    {
        $this->rules[] = $rule instanceof Closure ? new CallbackRule($rule) : $rule;

        return $this;
    }

    public function allow(Target $target): self
    {
        return $this->rule(TargetRule::allow($target));
    }

    public function deny(Target $target): self
    {
        return $this->rule(TargetRule::deny($target));
    }

    /**
     * @param  list<class-string>  $resources
     */
    public function hideResources(array $resources): self
    {
        return $this->deny(Target::make()->kinds([GroupKind::Resource])->groups($resources));
    }

    /**
     * @param  list<class-string>  $pages
     */
    public function hidePages(array $pages): self
    {
        return $this->deny(Target::make()->kinds([GroupKind::Page])->groups($pages));
    }

    /**
     * @param  list<class-string>  $widgets
     */
    public function hideWidgets(array $widgets): self
    {
        return $this->deny(Target::make()->kinds([GroupKind::Widget])->groups($widgets));
    }

    /**
     * Hides every resource that manages one of the models.
     *
     * @param  list<class-string>  $models
     */
    public function hideModels(array $models): self
    {
        return $this->deny(Target::make()->models($models));
    }

    /**
     * Hides these abilities on every resource, such as "forceDelete".
     *
     * @param  list<string>  $actions
     */
    public function hideActions(array $actions): self
    {
        return $this->deny(Target::make()->kinds([GroupKind::Resource])->actions($actions));
    }

    /**
     * @param  list<string>  $permissions  Exact keys, such as "Reorder:Service".
     */
    public function hidePermissions(array $permissions): self
    {
        return $this->deny(Target::make()->permissions($permissions));
    }

    /**
     * Hides whole tabs, such as every widget permission.
     *
     * @param  list<GroupKind>  $kinds
     */
    public function hideKinds(array $kinds): self
    {
        return $this->deny(Target::make()->kinds($kinds));
    }

    /**
     * @param  Closure(PermissionDefinition, PermissionGroup, PermissionContext): bool  $condition
     */
    public function hideWhen(Closure $condition): self
    {
        return $this->rule(CallbackRule::denyWhen($condition));
    }

    /**
     * @param  list<class-string>  $resources
     */
    public function allowResources(array $resources): self
    {
        return $this->allow(Target::make()->kinds([GroupKind::Resource])->groups($resources));
    }

    /**
     * @param  list<class-string>  $models
     */
    public function allowModels(array $models): self
    {
        return $this->allow(Target::make()->models($models));
    }

    /**
     * @param  list<string>  $actions
     */
    public function allowActions(array $actions): self
    {
        return $this->allow(Target::make()->kinds([GroupKind::Resource])->actions($actions));
    }

    /**
     * @param  list<string>  $permissions
     */
    public function allowPermissions(array $permissions): self
    {
        return $this->allow(Target::make()->permissions($permissions));
    }

    /**
     * Shows no resource but these. Pages, widgets and custom permissions are
     * not affected.
     *
     * @param  list<class-string>  $resources
     */
    public function onlyResources(array $resources): self
    {
        return $this->rule(new OnlyRule(
            Target::make()->groups($resources),
            Target::make()->kinds([GroupKind::Resource]),
        ));
    }

    /**
     * Shows no resource but the ones managing these models.
     *
     * @param  list<class-string>  $models
     */
    public function onlyModels(array $models): self
    {
        return $this->rule(new OnlyRule(
            Target::make()->models($models),
            Target::make()->kinds([GroupKind::Resource]),
        ));
    }

    /**
     * Shows no resource ability but these.
     *
     * @param  list<string>  $actions
     */
    public function onlyActions(array $actions): self
    {
        return $this->rule(new OnlyRule(
            Target::make()->actions($actions),
            Target::make()->kinds([GroupKind::Resource]),
        ));
    }

    /**
     * Shows no permission of any kind but these.
     *
     * @param  list<string>  $permissions
     */
    public function onlyPermissions(array $permissions): self
    {
        return $this->rule(new OnlyRule(
            Target::make()->permissions($permissions),
            Target::make(),
        ));
    }

    /**
     * Combines the rules' decisions: any Deny wins, otherwise any Allow,
     * otherwise the policy abstains.
     */
    public function decide(PermissionDefinition $permission, PermissionGroup $group, PermissionContext $context): Decision
    {
        $decision = Decision::Abstain;

        foreach ($this->rules as $rule) {
            $ruling = $rule->decide($permission, $group, $context);

            if ($ruling === Decision::Deny) {
                return Decision::Deny;
            }

            if ($ruling === Decision::Allow) {
                $decision = Decision::Allow;
            }
        }

        return $decision;
    }

    /**
     * @return list<PermissionRule>
     */
    public function rules(): array
    {
        return $this->rules;
    }
}
