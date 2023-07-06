<?php

namespace App\Models\PKSanc;

use App\Models\Auth\User;
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

    public function getSprite(): string
    {
        $pokemon = $this->Pokemon();
        if ($pokemon->sprite === null) {
            $species = Pokemon::where('species', $pokemon->species)->where('form_index', 0)->first();
            if ($species->sprite === null) {
                return 'unknown_sprite.png';
            }

            $pokemon = $species;
        }

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

    public function Owner(): User
    {
        return $this->belongsTo(User::class, 'owner_uuid', 'uuid')->first();
    }

    public function Pokemon(): Pokemon
    {
        return $this->belongsTo(Pokemon::class, 'pokemon', 'pokemon')->first();
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
        return $this->hasOne(Stats::class, 'pokemon_uuid', 'uuid')->first();
    }

    public function ContestStats(): ContestStats
    {
        return $this->hasOne(ContestStats::class, 'pokemon_uuid', 'uuid')->first();
    }

    public function Moveset(): Moveset
    {
        return $this->hasOne(Moveset::class, 'pokemon_uuid', 'uuid')->first();
    }

    public function Origin(): Origin
    {
        return $this->hasOne(Origin::class, 'pokemon_uuid', 'uuid')->first();
    }

    public function Ribbons(): HasMany
    {
        return $this->hasMany(PokemonRibbons::class, 'uupokemon_uuidid', 'uuid');
    }
}
