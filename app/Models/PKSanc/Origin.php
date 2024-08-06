<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Database\Factories\Modules\PKSanc\OriginFactory;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

class Origin extends AbstractModel
{
    use HasFactory, HasTimestamps, HasUuids;

    /** { @inheritdoc } */
    protected $table = 'pksanc__origin';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';

    /** { @inheritdoc } */
    protected $casts = [
        'was_egg' => 'boolean',
    ];

    public function getOwner(): StoredPokemon
    {
        /** @var StoredPokemon $pokemon */
        $pokemon = $this->belongsTo(StoredPokemon::class, 'pokemon_uuid', 'uuid')->first();
        return $pokemon;
    }

    public function getTrainer(): Trainer
    {
        /** @var Trainer $trainer */
        $trainer = $this->trainer()->first();
        return $trainer;
    }

    /**
     * The relationship the trainer this save belongs to
     * @return HasOne The relationship
     */
    public function trainer(): HasOne {
        return $this->hasOne(Trainer::class, 'uuid', 'trainer_uuid');
    }

    public function getGame(): Game
    {
        /** @var Game $game */
        $game = $this->original_game()->first();
        return $game;
    }

    /**
     * The relationship for origin is from
     * @return BelongsTo The relationship
     */
    public function original_game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game', 'game');
    }

    /**
     * Sets which factory should be used
     */
    protected static function newFactory(): OriginFactory
    {
        return OriginFactory::new();
    }
}
