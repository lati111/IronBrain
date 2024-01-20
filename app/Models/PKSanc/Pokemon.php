<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string species Code for the pokemon species (eg. Ninetales, which covers Ninetales and it's Alolan form)
 * @property string species_name The display version of the species name
 * @property string pokemon The code for this specific pokemon, eg ninetalesalola
 * @property string form The code for this form of the species, eg alola for Alolan Ninetales
 * @property string form_name The display version of the form name
 * @property int form_index The index of this form, as stated in PokeAPI and PKHeX
 * @property int pokedex_id The pokedex number of this pokemon from the national dex
 * @property string primary_type The pokemon's primary typing, as per the Type model
 * @property string secondary_type The pokemon's secondary typing, as per the Type model. Can be same as primary type
 * @property int base_hp The pokemon's base HP stat
 * @property int base_atk The pokemon's base attack stat
 * @property int base_def The pokemon's base defense stat
 * @property int base_spa The pokemon's base special attack stat
 * @property int base_spd The pokemon's base special defense stat
 * @property int base_spe The pokemon's base speed stat
 * @property string sprite The default sprite for this pokemon. Can be null if sprite doesn't exist
 * @property string sprite_shiny The shiny sprite for this pokemon. Can be null if sprite doesn't exist
 * @property string sprite_female The female sprite for this pokemon. Can be null if sprite doesn't exist
 * @property string sprite_female_shiny The female shiny sprite for this pokemon. Can be null if sprite doesn't exist
 * @property int generation Which generation the pokemon was introduced in
 * @property string pokemon_type Which type of pokemon this is (eg form for Alolan Ninetales, and variant for Pink Minior)
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Pokemon extends Model
{
    use HasTimestamps;

    //TODO make on delete for sprites

    protected $table = 'pksanc__pokemon';
    protected $primaryKey = 'pokemon';
    public $incrementing = false;

    public function getName(): string {
        $name = $this->species_name;
        if ($this->form_name !== null) {
            $name = $this->form_name . " " . $this->species_name;
        }

        return $name;
    }

    public function PrimaryType(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'primary_type', 'type');
    }

    public function SecondaryType(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'secondary_type', 'type');
    }
}
