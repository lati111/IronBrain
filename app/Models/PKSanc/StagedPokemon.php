<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StagedPokemon extends Model
{
    use HasFactory;
    use HasTimestamps;
    use HasUuids;

    protected $table = 'pksanc__staged_pokemon';
    protected $primaryKey = 'uuid';

    public function getNewPokemon(): StoredPokemon
    {
        return $this->belongsTo(StoredPokemon::class, 'new_pokemon_uuid', 'uuid')->first();
    }

    public function getOldPokemon(): ?StoredPokemon
    {
        return $this->belongsTo(StoredPokemon::class, 'old_pokemon_uuid', 'uuid')->first();
    }
}
