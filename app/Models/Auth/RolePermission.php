<?php

namespace App\Models\Auth;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends AbstractModel
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'auth__role_permission';
}
