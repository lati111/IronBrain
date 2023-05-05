<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submenu extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'nav_submenu';
    protected $primaryKey = 'id';

    public function Nav(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'id', 'projectId');
    }
}
