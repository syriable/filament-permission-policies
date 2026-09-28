<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles;

use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Syriable\Filament\Plugins\PermissionPolicies\Filament\Concerns\HasPermissionPolicies;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\Pages\CreateRole;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\Pages\EditRole;
use Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Resources\Roles\Pages\ListRoles;

final class RoleResource extends ShieldRoleResource
{
    use HasPermissionPolicies;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            Select::make('guard_name')
                ->options(['web' => 'Member', 'admin' => 'Administrator'])
                ->default('admin')
                ->required()
                ->live(),
            self::getSelectAllFormComponent(),
            self::getShieldFormComponents(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
