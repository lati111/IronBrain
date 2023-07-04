<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StoredPokemon extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__stored_pokemon';
    protected $primaryKey = 'uuid';

    public function sprite(): ?string
    {
        $pokemon = $this->Pokemon();
        if ($this->gender === "F" && $pokemon->sprite_female !== null) {
            if ($this->is_shiny === true) {
                return $pokemon->sprite_female_shiny;
            }

            return $pokemon->sprite_female;
        }

        if ($this->is_shiny === true) {
            return $pokemon->sprite_shiny;
        }

        return $pokemon->sprite;
    }

    public function Owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_uuid', 'uuid');
    }

    public function Pokemon(): BelongsTo
    {
        return $this->belongsTo(Pokemon::class, 'pokemon', 'pokemon');
    }

    public function Nature(): Nature
    {
        return $this->belongsTo(Nature::class, 'nature', 'nature')->first();
    }

    public function Ability(): Ability
    {
        return $this->belongsTo(Ability::class, 'ability', 'ability')->first();
    }

    public function Pokeball(): Pokeball
    {
        return $this->belongsTo(Pokeball::class, 'pokeball', 'pokeball')->first();
    }

    public function HiddenPower(): Type
    {
        return $this->belongsTo(Type::class, 'hidden_power_type', 'type')->first();
    }

    public function TeraType(): Type
    {
        return $this->belongsTo(Type::class, 'tera_type', 'type')->first();
    }

    public function Csv(): ImportCsv
    {
        return $this->belongsTo(ImportCsv::class, 'import_csv', 'csv')->first();
    }

    public function Stats(): Stats
    {
        return $this->hasOne(Stats::class, 'uuid', 'pokemon_uuid')->first();
    }

    public function ContestStats(): ContestStats
    {
        return $this->hasOne(ContestStats::class, 'uuid', 'pokemon_uuid')->first();
    }

    public function Moveset(): Moveset
    {
        return $this->hasOne(Moveset::class, 'uuid', 'pokemon_uuid')->first();
    }

    public function Origin(): Origin
    {
        return $this->hasOne(Origin::class, 'uuid', 'pokemon_uuid')->first();
    }

    public function Ribbons(): HasMany
    {
        return $this->hasMany(PokemonRibbons::class, 'uuid', 'pokemon_uuid');
    }
}
