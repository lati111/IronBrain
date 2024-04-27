<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Database\Factories\Modules\PKSanc\ContestStatblockFactory;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string pokemon_uuid The uuid of the pokemon these stats belong to
 * @property int beauty The value for the beauty stat of this pokemon
 * @property int cool The value for the cool stat of this pokemon
 * @property int cute The value for the cute stat of this pokemon
 * @property int smart The value for the smart stat of this pokemon
 * @property int tough The value for the tough stat of this pokemon
 * @property int sheen The value for the sheen stat of this pokemon
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class ContestStats extends AbstractModel
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__contest_stats';
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
    protected static function newFactory(): ContestStatblockFactory
    {
        return ContestStatblockFactory::new();
    }
}
