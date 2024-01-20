<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Move extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__move';
    protected $primaryKey = 'move';
    public $incrementing = false;

    public function Uploader(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type', 'type');
    }
}
