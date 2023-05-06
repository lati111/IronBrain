<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'auth__permission';
    protected $primaryKey = 'permission';
    protected $keyType = 'string';
}
