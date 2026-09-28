<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\PermissionPolicies\Tests\Fixtures\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 */
final class User extends Authenticatable implements FilamentUser
{
    use HasRoles;

    protected $guarded = [];

    protected $table = 'users';

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
