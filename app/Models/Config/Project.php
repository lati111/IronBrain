<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'nav__project';

    public function Submenu(): HasMany
    {
        return $this->hasMany(Submenu::class, 'project_id');
    }
}
