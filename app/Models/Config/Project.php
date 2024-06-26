<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'nav__project';

    public function Submenu(): HasMany
    {
        $role_id = null;
        $user = Auth::user();
        if ($user !== null) {
            $role_id = $user->role_id;
        }

        return $this
            ->hasMany(Submenu::class, 'project_id')
            ->select('nav__submenu.*')
            ->leftJoin(
                'auth__permission',
                'nav__submenu.permission_id',
                '=',
                'auth__permission.permission')
            ->where(function ($query) use ($role_id) {
                $query
                    ->where('nav__submenu.permission_id', null)
                    ->orWhere(function ($query) use ($role_id) {
                        return $query
                            ->select('auth__role.is_admin')
                            ->from('auth__role')
                            ->where('auth__role.id', $role_id);
                    }, 1)
                    ->orWhere(function ($query) use ($role_id) {
                        return $query
                            ->selectRaw('count(auth__role_permission.permission_id)')
                            ->from('auth__role_permission')
                            ->whereColumn('auth__role_permission.permission_id', 'nav__submenu.permission_id')
                            ->where('auth__role_permission.role_id', $role_id);
                    }, 1);
            })
            ->orderBy('order', 'asc');
    }

    public function Permission(): BelongsTo
    {
        return $this->belongsTo(BelongsTo::class, 'permission_id');
    }
}
