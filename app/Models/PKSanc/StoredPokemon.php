<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use App\Models\Auth\User;
use Database\Factories\Modules\PKSanc\StoredPokemonFactory;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string PID The pokemon's identifier as provided by PKHeX
 * @property string uuid
 * @property ?string nickname The given nickname of this pokemon. Identical to pokemon name if none is given
 * @property string pokemon Which pokemon species this pokemon is
 * @property string gender Which gender this pokemon is. Either 'M', 'F' or '-'
 * @property string nature Which nature this pokemon has
 * @property string ability Which ability this pokemon has
 * @property string pokeball Which pokeball this pokemon was caught with
 * @property string hidden_power_type Which type this pokemon's hidden power is
 * @property string tera_type Which tera type this pokemon has. If not defined, then it matches the primary type
 * @property int friendship The pokemon's friendship level. Ranges from 0 to 255
 * @property int level The pokemon's level. Ranges from 0 to 100
 * @property int height How tall the pokemon is
 * @property int weight How heavy the pokemon is
 * @property bool is_shiny If the pokemon is shiny or not
 * @property bool is_alpha If the pokemon is an alpha or not
 * @property bool has_n_sparkle If the pokemon has the N sparkle or not
 * @property bool can_gigantamax If this pokemon has gigantamaxing unlocked
 * @property int dynamax_level This pokemon's dynamax level. Ranged from 0 to 10
 * @property string csv_uuid The uuid of the csv this pokemon was imported from
 * @property int csv_line On which line of the csv this pokemon's data exists
 * @property string owner_uuid Uuid of the user that owns this pokemon
 * @property string validated_at The date this pokemon was validated at
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class StoredPokemon extends AbstractModel
{
    use HasFactory;
    use HasTimestamps;
    use HasUuids;

    /** { @inheritdoc } */
    protected $table = 'pksanc__stored_pokemon';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';

    /** { @inheritdoc } */
    protected $casts = [
        'is_shiny' => 'boolean',
        'is_alpha' => 'boolean',
        'has_n_sparkle' => 'boolean',
        'can_gigantamax' => 'boolean',
    ];

    /**
     * Gets the path to the sprite that should be used for this pokemon
     * @return string Returns the path to the sprite
     */
    public function getSprite(): string
    {
        $pokemon = $this->getPokemon();

        //if pokemon has no sprites, attempt to use species sprites instead
        if ($pokemon->sprite === null) {
            $species = Pokemon::where('species', $pokemon->species)->where('form_index', 0)->first();
            if ($species->sprite === null) {
                return 'unknown_sprite.png';
            }

            $pokemon = $species;
        }

        if ($this->gender === "F" && $pokemon->sprite_female !== null) {
            if ($this->is_shiny === true && $pokemon->sprite_female_shiny !== null) {
                return $pokemon->sprite_female_shiny;
            }

            return $pokemon->sprite_female;
        }

        if ($this->is_shiny === true && $pokemon->sprite_shiny !== null) {
            return $pokemon->sprite_shiny;
        }

        if ($pokemon->sprite !== null) {
            return $pokemon->sprite;
        }

        return 'unknown_sprite.png';
    }

    /**
     * Gets the owner of this pokemon
     * @return User Returns the owner of the pokemon
     */
    public function getOwner(): User
    {
        /** @var User $user */
        $user = $this->belongsTo(User::class, 'owner_uuid', 'uuid')->first();
        return $user;
    }

    /**
     * Gets the pokemon model of this pokemon
     * @return Pokemon Returns the owner of the pokemon
     */
    public function getPokemon(): Pokemon
    {
        /** @var Pokemon $pokemon */
        $pokemon = $this->pokemon()->first();
        return $pokemon;
    }

    /**
     * The relationship for the pokemon this is
     * @return BelongsTo The relationship
     */
    public function pokemon(): BelongsTo {
        return $this->belongsTo(Pokemon::class, 'pokemon', 'pokemon');
    }

    /**
     * Gets the nature of this pokemon
     * @return Nature Returns the nature of the pokemon
     */
    public function getNature(): Nature
    {
        /** @var Nature $nature */
        $nature = $this->nature()->first();
        return $nature;
    }

    /**
     * The relationship for nature this pokemon has
     * @return BelongsTo The relationship
     */
    public function nature(): BelongsTo {
        return $this->belongsTo(Nature::class, 'nature', 'nature');
    }

    /**
     * Gets the ability of this pokemon
     * @return Ability Returns the ability of the pokemon
     */
    public function getAbility(): Ability
    {
        /** @var Ability $ability */
        $ability = $this->ability()->first();
        return $ability;
    }

    /**
     * The relationship for ability this pokemon has
     * @return BelongsTo The relationship
     */
    public function ability(): BelongsTo {
        return $this->belongsTo(Ability::class, 'ability', 'ability');
    }

    /**
     * Gets the pokeball of this pokemon
     * @return Pokeball Returns the pokeball of the pokemon
     */
    public function getPokeball(): Pokeball
    {
        /** @var Pokeball $pokeball */
        $pokeball = $this->pokeball()->first();
        return $pokeball;
    }

    /**
     * The relationship for the pokeball this pokemon is in
     * @return BelongsTo The relationship
     */
    public function pokeball(): BelongsTo {
        return $this->belongsTo(Pokeball::class, 'pokeball', 'pokeball');
    }

    /**
     * Gets the hidden power type of this pokemon
     * @return Type Returns the type of hidden power the pokemon has
     */
    public function getHiddenPowerType(): Type
    {
        /** @var Type $hiddenPower */
        $hiddenPower = $this->hidden_power_type()->first();
        return $hiddenPower;
    }

    /**
     * The relationship for the hidden power type of this pokemon
     * @return BelongsTo The relationship
     */
    public function hidden_power_type(): BelongsTo {
        return $this->belongsTo(Type::class, 'hidden_power_type', 'type');
    }

    /**
     * Gets a pokemon's tera typing
     * @return Type Returns the tera typing
     */
    public function getTeraType(): Type
    {
        /** @var Type $teraType */
        $teraType = $this->tera_type()->first();
        return $teraType;
    }

    /**
     * The relationship for the tera type of this pokemon
     * @return BelongsTo The relationship
     */
    public function tera_type(): BelongsTo {
        return $this->belongsTo(Type::class, 'tera_type', 'type');
    }

    /**
     * Gets the csv this pokemon was imported from
     * @return ImportCsv Returns the csv this pokemon was imported from
     */
    public function getCsv(): ImportCsv
    {
        /** @var ImportCsv $csv */
        $csv = $this->csv()->first();
        return $csv;
    }

    /**
     * The relationship for this pokemon's import csv
     * @return BelongsTo The relationship
     */
    public function csv(): BelongsTo {
        return $this->belongsTo(ImportCsv::class, 'csv_uuid', 'uuid');
    }

    /**
     * Gets the origin of the pokemon
     * @return Origin Returns the pokemon's origin
     */
    public function getOrigin(): Origin
    {
        /** @var Origin $origin */
        $origin = $this->origin()->first();
        return $origin;
    }

    /**
     * The relationship for this pokemon's origin
     * @return HasOne The relationship
     */
    public function origin(): HasOne {
        return $this->hasOne(Origin::class, 'pokemon_uuid', 'uuid');
    }

    /**
     * Gets the statblock of the pokemon
     * @return Stats Returns the pokemon's stats
     */
    public function getStats(): Stats
    {
        /** @var Stats $stats */
        $stats = $this->hasOne(Stats::class, 'pokemon_uuid', 'uuid')->first();
        return $stats;
    }

    /**
     * Gets the contest statblock of the pokemon
     * @return ContestStats Returns the pokemon's contest stats
     */
    public function getContestStats(): ContestStats
    {
        /** @var ContestStats $contestStats */
        $contestStats = $this->hasOne(ContestStats::class, 'pokemon_uuid', 'uuid')->first();
        return $contestStats;
    }

    /**
     * Gets the contest statblock of the pokemon
     * @return Moveset Returns the pokemon's moveset
     */
    public function getMoveset(): Moveset
    {
        /** @var Moveset $moveset */
        $moveset = $this->hasOne(Moveset::class, 'pokemon_uuid', 'uuid')->first();
        return $moveset;
    }

    /**
     * Gets all the pokemon's ribbons
     * @return HasMany Returns the HasMany relationship for ribbons
     */
    public function Ribbons(): HasMany
    {
        return $this->hasMany(PokemonRibbons::class, 'pokemon_uuid', 'uuid');
    }

    /**
     * Gets the staging model
     * @return ?StagedPokemon Returns staging model if any exists
     */
    public function getStaging(): ?StagedPokemon
    {
        /** @var ?StagedPokemon $stagedPokemon */
        $stagedPokemon = $this->hasOne(StagedPokemon::class, 'new_pokemon_uuid', 'uuid')->first();
        return $stagedPokemon;
    }

    /**
     * The relationship to the previous version of this pokemon
     * @return HasOne The relationship
     */
    public function previous_version(): HasOne {
        return $this->hasOne(StagedPokemon::class, 'new_pokemon_uuid');
    }

    /**
     * Sets which factory should be used
     */
    protected static function newFactory(): StoredPokemonFactory
    {
        return StoredPokemonFactory::new();
    }
}
