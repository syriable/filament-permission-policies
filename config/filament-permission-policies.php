<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Role guards
    |--------------------------------------------------------------------------
    |
    | The guards roles can be created for, in the order the role form offers
    | them. Each key must be a guard defined in config/auth.php. Leave the
    | list empty to offer every guard in config/auth.php with no rules.
    |
    | label  A translation key or plain text. Defaults to the guard name.
    | color  A Filament color name for the guard badge in tables.
    | hide   Removed from the role form of this guard.
    | only   When set, nothing else of that type is shown for this guard.
    | allow  Shown even when the "global" section below hides it.
    |
    | Rule types, for hide, only and allow:
    |
    |   resources    Resource classes.
    |   pages        Page classes.                 (hide, allow)
    |   widgets      Widget classes.               (hide, allow)
    |   models       Model classes: every resource that manages them.
    |   actions      Resource abilities: 'forceDelete', 'reorder', ...
    |   permissions  Exact keys: 'Delete:Order', ...
    |   kinds        Whole tabs: 'resource', 'page', 'widget', 'custom'. (hide)
    |
    | Class names must exist and unknown keys throw, so a typo fails loudly
    | instead of showing what was meant to be hidden. An empty list is
    | ignored. Inside one guard a hidden item stays hidden, whatever "allow"
    | says. See the README for the full precedence rules.
    |
    */

    'guards' => [

        // 'admin' => [
        //     'label' => 'Administrator',
        //     'color' => 'primary',
        // ],

        // 'web' => [
        //     'label' => 'Member',
        //     'color' => 'gray',
        //     'hide' => [
        //         'resources' => [App\Filament\Resources\Roles\RoleResource::class],
        //         'actions' => ['restore', 'restoreAny', 'forceDelete', 'forceDeleteAny', 'replicate', 'reorder'],
        //     ],
        // ],

        // 'api' => [
        //     'label' => 'API',
        //     'color' => 'info',
        //     'hide' => [
        //         'kinds' => ['page', 'widget'],
        //     ],
        //     'only' => [
        //         'actions' => ['viewAny', 'view', 'create', 'update', 'delete'],
        //     ],
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global rules
    |--------------------------------------------------------------------------
    |
    | Applied to every guard, with the same rule types as above. A guard's
    | own "allow" brings back something hidden here.
    |
    */

    'global' => [
        'hide' => [],
        'only' => [],
        'allow' => [],
    ],

];
