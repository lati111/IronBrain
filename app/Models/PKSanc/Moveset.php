<?php

namespace App\Models\PKSanc;

use Database\Factories\Modules\PKSanc\MovesetFactory;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string pokemon_uuid The uuid of the pokemon these stats belong to
 * @property string move1 The code for the first move in the movepool
 * @property string move1_pp_up The amount of pp ups used for the first move in the movepool
 * @property string move2 The code for the second move in the movepool
 * @property string move2_pp_up The amount of pp ups used for the second move in the movepool
 * @property string move3 The code for the third move in the movepool
 * @property string move3_pp_up The amount of pp ups used for the third move in the movepool
 * @property string move4 The code for the fourth move in the movepool
 * @property string move4_pp_up The amount of pp ups used for the fourth move in the movepool
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Moveset extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__moveset';
    protected $primaryKey = 'pokemon_uuid';

    public function getOwner(): StoredPokemon
    {
        /** @var StoredPokemon $pokemon */
        $pokemon = $this->belongsTo(StoredPokemon::class, 'pokemon_uuid', 'uuid')->first();
        return $pokemon;
    }

    public function Move1(): Move
    {
        /** @var Move $move */
        $move = $this->belongsTo(Move::class, 'move1', 'move')->first();
        return $move;
    }

    public function Move2(): Move
    {
        /** @var Move $move */
        $move = $this->belongsTo(Move::class, 'move2', 'move')->first();
        return $move;
    }

    public function Move3(): Move
    {
        /** @var Move $move */
        $move = $this->belongsTo(Move::class, 'move3', 'move')->first();
        return $move;
    }

    public function Move4(): Move
    {
        /** @var Move $move */
        $move = $this->belongsTo(Move::class, 'move4', 'move')->first();
        return $move;
    }

    /**
     * Sets which factory should be used
     */
    protected static function newFactory(): MovesetFactory
    {
        return MovesetFactory::new();
    }
}
