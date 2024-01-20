<?php

namespace App\Models\PKSanc;

use Database\Factories\Modules\PKSanc\OriginFactory;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string uuid
 * @property string pokemon_uuid The uuid of the pokemon these stats belong
 * @property string trainer_uuid The uuid of the trainer that caught this pokemon
 * @property string game Which game this pokemon came from
 * @property string met_date Which date this pokemon was caught at
 * @property string met_location Which location this pokemon was caught at
 * @property int met_level The level this pokemon was caught at
 * @property bool was_egg Whether this pokemon was hatched from an egg
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Origin extends Model
{
    use HasFactory;
    use HasTimestamps;
    use HasUuids;

    protected $table = 'pksanc__origin';
    protected $primaryKey = 'uuid';

    public function getOwner(): StoredPokemon
    {
        /** @var StoredPokemon $pokemon */
        $pokemon = $this->belongsTo(StoredPokemon::class, 'pokemon_uuid', 'uuid')->first();
        return $pokemon;
    }

    public function GetPokemon(): Trainer
    {
        /** @var Trainer $trainer */
        $trainer = $this->belongsTo(Trainer::class, 'trainer_uuid', 'uuid')->first();
        return $trainer;
    }

    public function getGame(): Game
    {
        /** @var Game $game */
        $game = $this->belongsTo(Game::class, 'game', 'game')->first();
        return $game;
    }

    /**
     * Sets which factory should be used
     */
    protected static function newFactory(): OriginFactory
    {
        return OriginFactory::new();
    }
}
