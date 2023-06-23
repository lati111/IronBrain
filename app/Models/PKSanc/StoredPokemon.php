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

    public function Owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_uuid', 'uuid');
    }

    public function Pokemon(): BelongsTo
    {
        return $this->belongsTo(Pokemon::class, 'pokemon', 'pokemon');
    }

    public function Nature(): BelongsTo
    {
        return $this->belongsTo(Nature::class, 'nature', 'nature');
    }

    public function Ability(): BelongsTo
    {
        return $this->belongsTo(Ability::class, 'ability', 'ability');
    }

    public function Pokeball(): BelongsTo
    {
        return $this->belongsTo(Pokeball::class, 'pokeball', 'pokeball');
    }

    public function HiddenPower(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'hidden_power_type', 'type');
    }

    public function TeraType(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'tera_type', 'type');
    }

    public function Csv(): BelongsTo
    {
        return $this->belongsTo(ImportCsv::class, 'import_csv', 'csv');
    }

    public function Stats(): HasOne
    {
        return $this->hasOne(Stats::class, 'uuid', 'pokemon_uuid');
    }

    public function ContestStats(): HasOne
    {
        return $this->hasOne(ContestStats::class, 'uuid', 'pokemon_uuid');
    }

    public function Moveset(): HasOne
    {
        return $this->hasOne(Moveset::class, 'uuid', 'pokemon_uuid');
    }

    public function Origin(): HasOne
    {
        return $this->hasOne(Origin::class, 'uuid', 'pokemon_uuid');
    }

    public function Ribbons(): HasMany
    {
        return $this->hasMany(PokemonRibbons::class, 'uuid', 'pokemon_uuid');
    }
}
