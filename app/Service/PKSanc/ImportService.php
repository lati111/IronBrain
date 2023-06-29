<?php

namespace App\Service\PKSanc;

use App\Enum\PKSanc\PokemonTypes;
use App\Models\PKSanc\Ability;
use App\Models\PKSanc\Move;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\Pokeball;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\Type;
use Illuminate\Support\Facades\Storage;

class ImportService
{
    public function importType(array $typeData): bool
    {
        $type = Type::where('type', $typeData["type"])->first();
        if ($type === null) {
            $type = new Type;
            $type->type = $typeData["type"];
        }

        $type->name = $typeData["name"][0]["name"];
        $type->save();

        return $type->wasChanged();
    }

    public function importNature(array $natureData): bool
    {
        $nature = Nature::where('nature', $natureData['nature'])->first();
        if ($nature === null) {
            $nature = new Nature;
            $nature->nature = $natureData['nature'];
        }

        $nature->name = $natureData['name'][0]['name'];

        $atk = 1.00;
        if ($natureData['increased_stat'] === 2) {
            $atk = 1.10;
        } else if ($natureData['decreased_stat'] === 2) {
            $atk = 0.90;
        }

        if ($nature->atk_modifier != $atk) {
            $nature->atk_modifier = $atk;
        }

        $def = 1.00;
        if ($natureData['increased_stat'] === 3) {
            $def = 1.10;
        } else if ($natureData['decreased_stat'] === 3) {
            $def = 0.90;
        }

        if ($nature->def_modifier != $def) {
            $nature->def_modifier = $def;
        }

        $spa = 1.00;
        if ($natureData['increased_stat'] === 4) {
            $spa = 1.10;
        } else if ($natureData['decreased_stat'] === 4) {
            $spa = 0.90;
        }

        if ($nature->spa_modifier != $spa) {
            $nature->spa_modifier = $spa;
        }

        $spd = 1.00;
        if ($natureData['increased_stat'] === 5) {
            $spd = 1.10;
        } else if ($natureData['decreased_stat'] === 5) {
            $spd = 0.90;
        }

        if ($nature->spd_modifier != $spd) {
            $nature->spd_modifier = $spd;
        }

        $spe = 1.00;
        if ($natureData['increased_stat'] === 6) {
            $spe = 1.10;
        } else if ($natureData['decreased_stat'] === 6) {
            $spe = 0.90;
        }

        if ($nature->spe_modifier != $spe) {
            $nature->spe_modifier = $spe;
        }

        $nature->save();

        return $nature->wasChanged();
    }

    public function importMove(array $moveData): bool
    {
        $move = Move::where('move', $moveData['move'])->first();
        if ($move === null) {
            $move = new Move;
            $move->move = $moveData['move'];
        }

        $move->name = $moveData['name'][0]['name'];
        $move->power = $moveData['power'];
        $move->accuracy = $moveData['accuracy'];
        $move->priority = $moveData['priority'];
        $move->type = $moveData['type']['type'];
        $move->move_type = $moveData['move_type']['move_type'];
        $move->description = ($moveData['description'] === null) ? $moveData['description'][0]['description'] : "";
        $move->save();

        return $move->wasChanged();
    }

    public function importAbility(array $abilityData): bool
    {
        $ability = Ability::where('ability', $abilityData['ability'])->first();
        if ($ability === null) {
            $ability = new Ability;
            $ability->ability = $abilityData['ability'];
        }

        $ability->name = $abilityData['name'][0]['name'];
        $ability->description = ($abilityData['description'] !== []) ? $abilityData['description'][0]['description'] : "";
        $ability->save();

        return $ability->wasChanged();
    }

    public function importPokeball(array $pokeballData): bool
    {
        $pokeball = Pokeball::where('pokeball', $pokeballData['pokeball'])->first();
        if ($pokeball === null) {
            $pokeball = new Pokeball;
            $pokeball->pokeball = $pokeballData['pokeball'];
        }

        $pokeball->sprite = $this->importSprite(
            json_decode($pokeballData['sprite_string'][0]['sprite_string'], true)['default'],
            'pokeball',
        );

        $pokeball->name = $pokeballData['name'][0]['name'];
        $pokeball->save();

        return $pokeball->wasChanged();
    }

    public function importPokemon(array $pokemonData): bool
    {
        $pokemon = Pokemon::where('pokemon', $pokemonData['pokemon'])->first();
        if ($pokemon === null) {
            $pokemon = new Pokemon;
            $pokemon->pokemon = $pokemonData['pokemon'];
        }

        $pokemon->form_index = $pokemonData['form_index'] - 1;
        $pokemon->form = ($pokemonData['form_name'] !== "") ? $pokemonData['form_name'] : null;
        $pokemon->form_name = ($pokemon->form !== null) ? $this->formatText($pokemonData['form_name']) : null;
        $pokemon->species = $pokemonData['details']['species']['species'];
        $pokemon->species_name = ($pokemonData['details']['species']['name'][0]['name'] !== "") ? $pokemonData['details']['species']['name'][0]['name'] : null;

        $pokemon->primary_type = $pokemonData['details']['types'][0]['type']['type'];
        $pokemon->secondary_type = (isset($pokemonData['details']['types'][1])) ? $pokemonData['details']['types'][1]['type']['type'] : $pokemon->primary_type;
        $pokemon->base_hp = $pokemonData['details']['stats'][0]['value'];
        $pokemon->base_atk = $pokemonData['details']['stats'][1]['value'];
        $pokemon->base_def = $pokemonData['details']['stats'][2]['value'];
        $pokemon->base_spa = $pokemonData['details']['stats'][3]['value'];
        $pokemon->base_spd = $pokemonData['details']['stats'][4]['value'];
        $pokemon->base_spe = $pokemonData['details']['stats'][5]['value'];

        $sprites = $pokemonData['sprites'][0]['sprites'];
        $sprites = json_decode($sprites, true);

        $pokemon->sprite = $this->importSprite(
            $sprites['front_default'],
            'pokemon',
            sprintf('%s_default.png', $pokemon->pokemon)
        );

        $pokemon->sprite_shiny = $this->importSprite(
            $sprites['front_shiny'],
            'pokemon',
            sprintf('%s_shiny.png', $pokemon->pokemon)
        );

        $pokemon->sprite_female = $this->importSprite(
            $sprites['front_female'],
            'pokemon',
            sprintf('%s_female.png', $pokemon->pokemon)
        );

        $pokemon->sprite_female_shiny = $this->importSprite(
            $sprites['front_shiny_female'],
            'pokemon',
            sprintf('%s_shiny_female.png', $pokemon->pokemon)
        );

        $pokemon->pokedex_id = $pokemonData['pokedex_id'];
        $pokemon->generation = (isset($pokemonData['generation'][0])) ? $pokemonData['generation'][0]['generation'] : $pokemonData['generation_backup']['generation'];
        $pokemon->pokemon_type = PokemonTypes::FORM;
        $pokemon->save();

        return $pokemon->wasChanged();
    }

    private function importSprite(?string $spriteString, string $spriteType, string $fileName = null): ?string
    {
        if ($fileName === null) {
            $arr = explode('/', $spriteString);
            $fileName = end($arr);
        }

        if ($spriteString !== null) {
            $spriteUrl = str_replace('/media/', 'https://raw.githubusercontent.com/PokeAPI/sprites/master/', $spriteString);
            $spritePath = sprintf('project/pksanc/%s/%s', $spriteType, $fileName);
            if (Storage::missing($spritePath)) {
                $contents = file_get_contents($spriteUrl);
                if (getimagesizefromstring($contents) !== false) {
                    Storage::put($spritePath, $contents);
                }
            }

            return $fileName;
        }

        return null;
    }

    private function formatText(string $oldText): string
    {
        $text = str_replace('-', ' ', $oldText);
        $text = ucfirst($text);
        return $text;
    }
}
