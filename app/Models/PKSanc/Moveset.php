<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Moveset extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__moveset';
    protected $primaryKey = 'pokemon_uuid';

    public function Move1(): BelongsTo
    {
        return $this->belongsTo(Move::class, 'move1', 'move');
    }

    public function Move2(): BelongsTo
    {
        return $this->belongsTo(Move::class, 'move2', 'move');
    }

    public function Move3(): BelongsTo
    {
        return $this->belongsTo(Move::class, 'move3', 'move');
    }

    public function Move4(): BelongsTo
    {
        return $this->belongsTo(Move::class, 'move4', 'move');
    }
}
