<?php

namespace App\Models\Config;

use App\Models\AbstractModel;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\RolePermission;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * @inheritdoc
 * @property int id
 * @property string code The unique code identifying this module
 * @property string name The display name for the module
 * @property string description The description given to the module
 * @property string|null thumbnail The thumbnail used for the module in the home overview
 * @property string|null route The route leading to the main page for this module
 * @property int|null permission_id The id for permission a user needs to have to access the module, if any
 * @property int order The sorting order for this module used during displaying
 * @property bool in_overview Whether this module should be visible on the overview
 * @property bool in_nav Whether this module should be visible on the nav
 * @property Carbon|null deleted_at Whether or not the item was deleted or not
 */

class Module extends AbstractModel
{
    use HasFactory, HasTimestamps, SoftDeletes;

    /** { @inheritdoc } */
    protected $table = 'module__main';

    /** { @inheritdoc } */
    protected $primaryKey = 'id';

    /**
     * Get the has many relationship for the module's submodules
     * @return HasMany The relationship
     */
    public function submodules(): HasMany
    {
        $user = Auth::user();
        $role_id = $user?->role_id;

        return $this
            ->hasMany(Submodule::class, 'module_id')
            ->select(Submodule::getTableName().'.*')
            ->leftJoin(
                Permission::getTableName(),
                sprintf('%s.permission_id', Submodule::getTableName()),
                '=',
                sprintf('%s.permission', Permission::getTableName()),
            )->where(function ($query) use ($role_id) {
                $query
                    ->where(sprintf('%s.permission_id', Submodule::getTableName()), null)
                    ->orWhere(function ($query) use ($role_id) {
                        return $query
                            ->select(sprintf('%s.is_admin', Role::getTableName()))
                            ->from(Role::getTableName())
                            ->where(sprintf('%s.id', Role::getTableName()), $role_id);
                    }, 1)
                    ->orWhere(function ($query) use ($role_id) {
                        return $query
                            ->selectRaw(sprintf('count(%s.permission_id)', RolePermission::getTableName()))
                            ->from(RolePermission::getTableName())
                            ->whereColumn(sprintf('%s.permission_id', RolePermission::getTableName()), sprintf('%s.permission_id', Submodule::getTableName()))
                            ->where(sprintf('%s.role_id', RolePermission::getTableName()), $role_id);
                    }, 1);
            })->orderBy('order');
    }

    /**
     * The belongs to relationship to the required permission
     * @return BelongsTo The relationship
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(BelongsTo::class, 'permission_id');
    }
}
