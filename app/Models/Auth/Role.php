<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Role extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'auth__role';

    /** { @inheritdoc } */
    protected $casts = [
        'is_admin' => 'boolean',
    ];

    public function hasPermission(Permission $permission): int
    {
        return $this
            ->hasMany(RolePermission::class, 'role_id')
            ->where('permission_id', $permission->id)
            ->count();
    }

    public function Users(): HasMany {
        return $this->hasMany(User::class);
    }
}
