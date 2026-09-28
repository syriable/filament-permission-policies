<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Enums;

/**
 * The kind of entity a permission group was generated for. It mirrors the
 * tabs Shield shows on a role: resources, pages, widgets and custom ones.
 */
enum GroupKind: string
{
    case Resource = 'resource';
    case Page = 'page';
    case Widget = 'widget';
    case Custom = 'custom';
}
