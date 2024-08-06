<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagedPokemon extends AbstractModel
{
    use HasFactory;
    use HasTimestamps;
    use HasUuids;

    protected $table = 'pksanc__staged_pokemon';
    protected $primaryKey = 'uuid';

    public function getNewPokemon(): StoredPokemon {
        return $this->new_pokemon->first();
    }

    public function new_pokemon(): BelongsTo
    {
        return $this->belongsTo(StoredPokemon::class, 'new_pokemon_uuid', 'uuid')->first();
    }

    public function getOldPokemon(): StoredPokemon {
        return $this->old_pokemon_uuid->first();
    }

    public function old_pokemon(): BelongsTo
    {
        return $this->belongsTo(StoredPokemon::class, 'old_pokemon_uuid', 'uuid');
    }
}
