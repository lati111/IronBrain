<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Database\Factories\Modules\PKSanc\MovesetFactory;
use Database\Factories\Modules\PKSanc\StatblockFactory;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string pokemon_uuid The uuid of the pokemon these stats belong to
 * @property int hp_iv The IV value for the HP stat of this pokemon
 * @property int hp_ev The EV value for the HP stat of this pokemon
 * @property int atk_iv The IV value for the attack stat of this pokemon
 * @property int atk_ev The EV value for the attack stat of this pokemon
 * @property int def_iv The IV value for the defense stat of this pokemon
 * @property int def_ev The EV value for the defense stat of this pokemon
 * @property int spa_iv The IV value for the special attack stat of this pokemon
 * @property int spa_ev The EV value for the special attack stat of this pokemon
 * @property int spd_iv The IV value for the special defense stat of this pokemon
 * @property int spd_ev The EV value for the special defense stat of this pokemon
 * @property int spe_iv The IV value for the speed stat of this pokemon
 * @property int spe_ev The EV value for the speed stat of this pokemon
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Stats extends AbstractModel
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__stats';
    protected $primaryKey = 'pokemon_uuid';

    public function getOwner(): StoredPokemon
    {
        /** @var StoredPokemon $pokemon */
        $pokemon = $this->belongsTo(StoredPokemon::class, 'pokemon_uuid', 'uuid')->first();
        return $pokemon;
    }

    /**
     * Sets which factory should be used
     */
    protected static function newFactory(): StatblockFactory
    {
        return StatblockFactory::new();
    }
}
