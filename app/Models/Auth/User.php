<?php

namespace App\Models\Auth;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\AbstractModel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string uuid
 * @property string username The username for this user
 * @property ?string pronouns Which pronouns to display on the user's profile
 * @property ?string description Bio as defined by the user
 * @property string email The email address this user is registered with
 * @property ?string email_verified_at Date the email was verified at. Null if unverified
 * @property string password The hashed user password
 * @property string profile_picture Path to the user's profile picture
 * @property ?int role_id The ID of the role the user has, null if no role is set
 * @property ?string remember_token The token responsible for keeping a user logged in. Can be null
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 * @property string deleted_at The date this model was deleted at
 */

class User extends AbstractModel implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;
    use Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail;

    /** { @inheritdoc } */
    protected $table = 'auth__user';

    /** { @inheritdoc } */
    protected $primaryKey='uuid';

    /** { @inheritdoc } */
    protected $fillable = [
        'username',
        'email',
        'password',
    ];

    /** { @inheritdoc } */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Validate that the user has a permission
     * @return bool Whether or not the user has the permission
     */
    public function hasPermission(string $permission): bool {
        $role = $this->role()->first();
        if ($role === null) {
            return false;
        }

        $permission = Permission::where('permission', $permission)->first();
        if ($permission === null) {
            return true;
        }

        return $role->hasPermission($permission);
    }

    /**
     * The has one relationship for the user's role
     * @return HasOne The relationship
     */
    public function role(): HasOne {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }
}
