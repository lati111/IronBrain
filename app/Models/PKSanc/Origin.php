<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Origin extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__origin';
    protected $primaryKey = 'pokemon_uuid';

    public function Trainer(): Trainer
    {
        return $this->belongsTo(Trainer::class, 'trainer_uuid', 'uuid')->first();
    }

    public function Game(): Game
    {
        return $this->belongsTo(Game::class, 'game', 'game')->first();
    }
}
