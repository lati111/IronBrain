<?php

namespace App\Service\PKSanc;

use App\Enum\PKSanc\PokemonTypes;
use App\Models\PKSanc\Ability;
use App\Models\PKSanc\Move;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\Pokeball;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\Type;
use App\Service\PKSanc\PokeApiService;
use Illuminate\Support\Facades\Storage;

class ImportService {
    private readonly PokeApiService $api;

    public function __construct (PokeApiService $api) {
        $this->api = $api;
    }

    public function importTypes(): void {
        $typeCollection = $this->api->getTypes();

        foreach($typeCollection as $typeData) {
            $type = Type::where('type', $typeData["type"])->first();
            if ($type === null) {
                $type = new Type;
                $type->type = $typeData["type"];
            }

            $type->name = $typeData["name"][0]["name"];
            $type->save();
        }
    }

    public function importNatures(): void {
        $natureCollection = $this->api->getNatures();

        foreach($natureCollection as $natureData) {
            $nature = Nature::where('nature', $natureData['nature'])->first();
            if ($nature === null) {
                $nature = new Nature;
                $nature->nature = $natureData['nature'];
            }

            $nature->name = $natureData['name'][0]['name'];
            $nature->atk_modifier = 1.0;
            $nature->def_modifier = 1.0;
            $nature->spa_modifier = 1.0;
            $nature->spd_modifier = 1.0;
            $nature->spe_modifier = 1.0;


            switch($natureData['increased_stat']) {
                case 2: //attack
                    $nature->atk_modifier = 1.1;
                    break;
                case 3: //defense
                    $nature->def_modifier = 1.1;
                    break;
                case 4: //special attack
                    $nature->spa_modifier = 1.1;
                    break;
                case 5: //special defense
                    $nature->spd_modifier = 1.1;
                    break;
                case 6: //speed
                    $nature->spe_modifier = 1.1;
                    break;
            }

            switch($natureData['decreased_stat']) {
                case 2: //attack
                    $nature->atk_modifier = 0.9;
                    break;
                case 3: //defense
                    $nature->def_modifier = 0.9;
                    break;
                case 4: //special attack
                    $nature->spa_modifier = 0.9;
                    break;
                case 5: //special defense
                    $nature->spd_modifier = 0.9;
                    break;
                case 6: //speed
                    $nature->spe_modifier = 0.9;
                    break;
            }
            $nature->save();
        }
    }

    public function importMoves(): void {
        $moveCollection = $this->api->getMoves();

        foreach($moveCollection as $moveData) {
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
        }
    }

    public function importAbilities(): void {
        $abilityCollection = $this->api->getAbilities();

        foreach($abilityCollection as $abilityData) {
            $ability = Ability::where('ability', $abilityData['ability'])->first();
            if ($ability === null) {
                $ability = new Ability;
                $ability->ability = $abilityData['ability'];
            }

            $ability->name = $abilityData['name'][0]['name'];
            $ability->description = ($abilityData['description'] !== []) ? $abilityData['description'][0]['description'] : "";
            $ability->save();
        }
    }

    public function importPokeballs(): void {
        $pokeballCollection = $this->api->getPokeballs();

        foreach($pokeballCollection as $pokeballData) {
            $pokeball = Pokeball::where('pokeball', $pokeballData['pokeball'])->first();
            if ($pokeball === null) {
                $pokeball = new Pokeball;
                $pokeball->pokeball = $pokeballData['pokeball'];
            }

            $pokeball->sprite = $this->importSprite(
                $pokeballData['sprite_string'][0]['sprite_string'],
                'pokeball',
            );

            $pokeball->name = $pokeballData['name'][0]['name'];
            $pokeball->save();
        }
    }

    public function importPokemon(): void {
        $pokemonCollection = $this->api->getPokemon();

        foreach($pokemonCollection as $pokemonData) {
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
        }
    }

    private function importSprite(?string $spriteString, string $spriteType, string $fileName = null): ?string {
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

    private function formatText(string $oldText) {
        $text = str_replace('-', ' ', $oldText);
        $text = ucfirst($text);
        return $text;
    }
}
