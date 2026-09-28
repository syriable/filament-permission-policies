<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Filament\Concerns;

use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionCatalog;
use Syriable\Filament\Plugins\PermissionPolicies\Data\PermissionGroup;
use Syriable\Filament\Plugins\PermissionPolicies\Enums\GroupKind;
use Syriable\Filament\Plugins\PermissionPolicies\Facades\PermissionPolicies;

/**
 * Renders Shield's role form from the permission catalog of the role's
 * context. Use it on a RoleResource that extends Shield's RoleResource.
 *
 * Every tab, section, checkbox list, badge and the "select all" toggle read
 * the same catalog, so nothing on the form can disagree about what exists.
 * The catalog is recomputed on each render: changing the role's guard on the
 * form switches the context immediately.
 */
trait HasPermissionPolicies
{
    public static function getShieldFormComponents(): Component
    {
        return Tabs::make('Permissions')
            ->contained()
            ->tabs([
                static::getPermissionPoliciesResourcesTab(),
                static::getPermissionPoliciesTab('pages', GroupKind::Page, __('filament-shield::filament-shield.pages')),
                static::getPermissionPoliciesTab('widgets', GroupKind::Widget, __('filament-shield::filament-shield.widgets')),
                static::getPermissionPoliciesTab('custom_permissions', GroupKind::Custom, __('filament-shield::filament-shield.custom')),
            ])
            ->columnSpanFull();
    }

    public static function getSelectAllFormComponent(): Component
    {
        return Toggle::make('select_all')
            ->onIcon('heroicon-s-shield-check')
            ->offIcon('heroicon-s-shield-exclamation')
            ->label(__('filament-shield::filament-shield.field.select_all.name'))
            ->helperText(fn (): HtmlString => new HtmlString((string) __('filament-shield::filament-shield.field.select_all.message')))
            ->live()
            ->afterStateUpdated(function (Toggle $component, Set $set, ?bool $state): void {
                foreach (static::getPermissionCheckboxListOptions(static::getPermissionCatalogFor($component)) as $name => $options) {
                    $set($name, $state === true ? array_keys($options) : []);
                }
            })
            ->dehydrated(false);
    }

    /**
     * The catalog for the role the form is editing, in its current state.
     */
    public static function getPermissionCatalogFor(Component $component): PermissionCatalog
    {
        $state = $component->getRootContainer()->getRawState();
        $record = $component->getRecord();

        return PermissionPolicies::forRole(
            $record instanceof Model ? $record : null,
            $state instanceof Arrayable ? $state->toArray() : $state,
        );
    }

    /**
     * Every checkbox list the form renders, keyed by state name, with its
     * options. The tabs are built from it and "select all" fills it, so the
     * two always agree.
     *
     * @return array<string, array<string, string>>
     */
    public static function getPermissionCheckboxListOptions(PermissionCatalog $catalog): array
    {
        $lists = [];

        foreach (GroupKind::cases() as $kind) {
            if (! static::isPermissionTabEnabled($kind)) {
                continue;
            }

            if ($kind === GroupKind::Resource && ! static::shield()->hasSimpleResourcePermissionView()) {
                foreach ($catalog->groups($kind) as $group) {
                    $lists[static::getPermissionGroupStateName($group)] = $group->options();
                }

                continue;
            }

            $lists[static::getPermissionTabStateName($kind)] = $catalog->options($kind);
        }

        return array_filter($lists, static fn (array $options): bool => $options !== []);
    }

    public static function getPermissionGroupStateName(PermissionGroup $group): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', $group->key));
    }

    /**
     * How many of the role's stored permissions its context presents: the
     * number to show in a roles table next to the form.
     */
    public static function countPresentedPermissions(Model $role): int
    {
        return PermissionPolicies::forRole($role)->selectedCount(static::getStoredPermissionNames($role));
    }

    protected static function getPermissionPoliciesResourcesTab(): Tab
    {
        return Tab::make('resources')
            ->label(__('filament-shield::filament-shield.resources'))
            ->visible(fn (Tab $component): bool => static::isPermissionTabEnabled(GroupKind::Resource)
                && static::getPermissionCatalogFor($component)->permissionCount(GroupKind::Resource) > 0)
            ->badge(fn (Tab $component): int => static::getPermissionCatalogFor($component)->permissionCount(GroupKind::Resource))
            ->schema(function (Tab $component): array {
                $catalog = static::getPermissionCatalogFor($component);

                if (static::shield()->hasSimpleResourcePermissionView()) {
                    return [static::getPermissionCheckboxList(static::getPermissionTabStateName(GroupKind::Resource), $catalog->options(GroupKind::Resource))];
                }

                return [
                    Grid::make()
                        ->schema(array_map(
                            static::getPermissionGroupSection(...),
                            $catalog->groups(GroupKind::Resource),
                        ))
                        ->columns(static::normalizePermissionColumns(static::shield()->getGridColumns())),
                ];
            });
    }

    protected static function getPermissionGroupSection(PermissionGroup $group): Section
    {
        return Section::make($group->label)
            ->description($group->model !== null && (bool) config('filament-shield.shield_resource.show_model_path', true)
                ? $group->model
                : null)
            ->compact()
            ->collapsible()
            ->schema([
                static::getPermissionCheckboxList(
                    static::getPermissionGroupStateName($group),
                    $group->options(),
                    searchable: false,
                    columns: static::shield()->getResourceCheckboxListColumns(),
                    columnSpan: static::shield()->getResourceCheckboxListColumnSpan(),
                ),
            ])
            ->columnSpan(static::shield()->getSectionColumnSpan());
    }

    protected static function getPermissionPoliciesTab(string $name, GroupKind $kind, mixed $label): Tab
    {
        return Tab::make($name)
            ->label(is_string($label) ? $label : $name)
            ->visible(fn (Tab $component): bool => static::isPermissionTabEnabled($kind)
                && static::getPermissionCatalogFor($component)->permissionCount($kind) > 0)
            ->badge(fn (Tab $component): int => static::getPermissionCatalogFor($component)->permissionCount($kind))
            ->schema(fn (Tab $component): array => [
                static::getPermissionCheckboxList(static::getPermissionTabStateName($kind), static::getPermissionCatalogFor($component)->options($kind)),
            ]);
    }

    protected static function isPermissionTabEnabled(GroupKind $kind): bool
    {
        return match ($kind) {
            GroupKind::Resource => Utils::isResourceTabEnabled(),
            GroupKind::Page => Utils::isPageTabEnabled(),
            GroupKind::Widget => Utils::isWidgetTabEnabled(),
            GroupKind::Custom => Utils::isCustomPermissionTabEnabled(),
        };
    }

    /**
     * The state name of the single list a tab shows; Shield's own names.
     */
    protected static function getPermissionTabStateName(GroupKind $kind): string
    {
        return match ($kind) {
            GroupKind::Resource => 'resources_tab',
            GroupKind::Page => 'pages_tab',
            GroupKind::Widget => 'widgets_tab',
            GroupKind::Custom => 'custom_permissions_tab',
        };
    }

    /**
     * @param  array<string, string>  $options
     * @param  array<array-key, mixed>|int|string|null  $columns
     * @param  array<array-key, mixed>|int|string|null  $columnSpan
     */
    protected static function getPermissionCheckboxList(
        string $name,
        array $options,
        bool $searchable = true,
        array|int|string|null $columns = null,
        array|int|string|null $columnSpan = null,
    ): CheckboxList {
        return CheckboxList::make($name)
            ->hiddenLabel()
            ->options($options)
            ->searchable($searchable)
            ->live()
            ->afterStateHydrated(function (CheckboxList $component, Get $get, Set $set, ?Model $record, string $operation) use ($options): void {
                if ($record instanceof Model && in_array($operation, ['edit', 'view'], true)) {
                    $component->state(array_values(array_intersect(array_keys($options), static::getStoredPermissionNames($record))));
                }

                static::syncSelectAllToggle($component, $get, $set);
            })
            ->afterStateUpdated(fn (CheckboxList $component, Get $get, Set $set) => static::syncSelectAllToggle($component, $get, $set))
            ->selectAllAction(fn (Action $action, CheckboxList $component, Get $get, Set $set): Action => static::configureBulkToggleAction($action, $component, $get, $set, array_keys($options)))
            ->deselectAllAction(fn (Action $action, CheckboxList $component, Get $get, Set $set): Action => static::configureBulkToggleAction($action, $component, $get, $set, []))
            ->bulkToggleable()
            ->dehydrated(fn (mixed $state): bool => filled($state))
            ->gridDirection('row')
            ->columns(static::normalizePermissionColumns($columns ?? static::shield()->getCheckboxListColumns()))
            ->columnSpan($columnSpan ?? static::shield()->getCheckboxListColumnSpan());
    }

    /**
     * @param  list<string>  $state
     */
    protected static function configureBulkToggleAction(Action $action, CheckboxList $component, Get $get, Set $set, array $state): Action
    {
        return $action
            ->livewireClickHandlerEnabled()
            ->action(function () use ($component, $get, $set, $state): void {
                $component->state($state);
                static::syncSelectAllToggle($component, $get, $set);
            });
    }

    protected static function syncSelectAllToggle(Component $component, Get $get, Set $set): void
    {
        $lists = static::getPermissionCheckboxListOptions(static::getPermissionCatalogFor($component));

        $isEverythingSelected = $lists !== [];

        foreach ($lists as $name => $options) {
            $selected = $get($name);
            $selected = is_array($selected) ? array_filter($selected, is_string(...)) : [];

            if (array_diff(array_keys($options), $selected) !== []) {
                $isEverythingSelected = false;

                break;
            }
        }

        $set('select_all', $isEverythingSelected);
    }

    /**
     * @return list<string>
     */
    protected static function getStoredPermissionNames(Model $role): array
    {
        $role->loadMissing('permissions');

        $permissions = $role->getRelationValue('permissions');
        $names = [];

        foreach (is_iterable($permissions) ? $permissions : [] as $permission) {
            $name = $permission instanceof Model ? $permission->getAttribute('name') : null;

            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Shield accepts column counts as strings too; Filament wants integers.
     *
     * @param  int|string|array<array-key, mixed>  $columns
     * @return array<string, int|null>|int|null
     */
    protected static function normalizePermissionColumns(int|string|array $columns): array|int|null
    {
        if (! is_array($columns)) {
            return is_numeric($columns) ? (int) $columns : null;
        }

        $normalized = [];

        foreach ($columns as $breakpoint => $count) {
            $normalized[(string) $breakpoint] = is_numeric($count) ? (int) $count : null;
        }

        return $normalized;
    }
}
