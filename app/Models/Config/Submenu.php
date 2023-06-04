<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submenu extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'nav__submenu';
    protected $primaryKey = 'id';

    public function Nav(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    public function Permission(): BelongsTo
    {
        return $this->belongsTo(BelongsTo::class, 'permission_id');
    }
}
