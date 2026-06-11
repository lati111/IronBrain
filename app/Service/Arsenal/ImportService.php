<?php

namespace App\Service\Arsenal;

use App\Enum\PKSanc\PokemonTypes;
use App\Models\Arsenal\Companion;
use App\Models\Arsenal\Component;
use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use App\Models\PKSanc\Ability;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\Move;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\Pokeball;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\Type;
use Illuminate\Support\Facades\Storage;

class ImportService
{
    private const string imageUrl = 'https://raw.githubusercontent.com/WFCD/warframe-items/refs/heads/master/data/img';

    /**
     * Sets the database connection used by the importer
     * @param string $connection The name of the connection
     */
    public function setConnection(string $connection): void {
        \Config::set('database.connections.mysql.database', $connection);
        \DB::purge('mysql');
    }

    /**
     * Parses the data from an array and saves them to the database as a warframe
     * @param array $data An array containing the data
     * @return bool Returns whether the warframe was changed/saved or not
     */
    public function importWarframe(array $data): bool
    {
        $updated = false;
        $warframe = Warframe::where('id', $data['uniqueName'])->first();

        // Handle blacklist
        if (
            ($data['productCategory'] ?? 'none') === 'MechSuits' ||
            $data['uniqueName'] === '/Lotus/Powersuits/PowersuitAbilities/Helminth'
        ) {
            if ($warframe === null) {
                return false;
            }

            $warframe->delete();
            return true;
        }

        // Create new
        if ($warframe === null) {
            $warframe = new Warframe();
            $warframe->id = $data['uniqueName'];
            $updated = true;
        }

        // Update contents
        $warframe->name = $data['name'];
        $warframe->description = $data['description'] ?? '';
        $warframe->wiki_url = $data['wikiaUrl'] ?? null;
        $warframe->prime = $data['isPrime'];
        $warframe->icon = isset($data['imageName']) ? $this->importAsset($data['imageName'], 'warframe') : null;
        $warframe->save();

        // Update components
        if (isset($data['components'])) {
            $occurrences = [];
            foreach ($data['components'] as $component) {
                $occurrences[$component['uniqueName']] = ($occurrences[$component['uniqueName']] ?? 0) + 1;
                $componentUpdated = $this->importComponent($component, $warframe, 'warframe', $occurrences[$component['uniqueName']]);
                $updated = $updated || $componentUpdated;
            }
        }

        // Link exalted weapons to this warframe
        if (isset($data['exaltedWeapons'])) {
            foreach ($data['exaltedWeapons'] as $exaltedWeaponId) {
                $exaltedWeapon = Weapon::where('id', $exaltedWeaponId)->first();
                if ($exaltedWeapon !== null && $exaltedWeapon->exalted_id !== $warframe->id) {
                    $exaltedWeapon->exalted_id = $warframe->id;
                    $exaltedWeapon->save();
                    $updated = true;
                }
            }
        }

        return $updated ? true : $warframe->wasChanged();
    }

    /**
     * Parses the data from an array and saves them to the database as a weapon
     * @param array $data An array containing the data
     * @return bool Returns whether the weapon was changed/saved or not
     */
    public function importWeapon(array $data): bool
    {
        $updated = false;
        $weapon = Weapon::where('id', $data['uniqueName'])->first();

        // Handle blacklist
        if (
            ($data['type'] ?? 'none') === 'Zaw Component' && !str_contains($data['uniqueName'], '/Tip/') ||
            ($data['type'] ?? 'none') === 'Zaw Component' && str_contains($data['uniqueName'], '/PvPVariant')
        ) {
            if ($weapon === null) {
                return false;
            }

            $weapon->delete();
            return true;
        }

        // Create new
        if ($weapon === null) {
            $weapon = new Weapon();
            $weapon->id = $data['uniqueName'];
            $updated = true;
        }

        // Mutate data
        if ($data['type'] === 'Companion Weapon') {
            $data['category'] = 'Companion Weapon';
        }

        // Update contents
        $weapon->name = $data['name'];
        $weapon->description = $data['description'] ?? '';
        $weapon->type = match (strtolower($data['category'])) {
            'arch-gun'         => 'archgun',
            'arch-melee'       => 'archmelee',
            'companion weapon' => 'companion_weapon',
            default            => strtolower($data['category']),
        };
        $weapon->weapon_type = $data['type'];
        $weapon->wiki_url = $data['wikiaUrl'] ?? null;
        $weapon->prime = $data['isPrime'] ?? false;
        $weapon->icon = isset($data['imageName']) ? $this->importAsset($data['imageName'], 'weapon') : null;
        $weapon->save();

        // Update components
        if (isset($data['components'])) {
            $componentsUpdates = false;
            $occurrences = [];
            foreach ($data['components'] as $component) {
                $occurrences[$component['uniqueName']] = ($occurrences[$component['uniqueName']] ?? 0) + 1;
                $componentUpdated = $this->importComponent($component, $weapon, 'weapon', $occurrences[$component['uniqueName']]);
                $componentsUpdates = $updated || $componentUpdated;
            }

            // Delete if the blueprint only has 1 component
            if (Component::where('blueprint_id', $weapon->id)->count() === 1) {
                Component::where('blueprint_id', $weapon->id)->delete();
            } else {
                $updated = $updated || $componentsUpdates;
            }
        }

        return $updated ? true : $weapon->wasChanged();
    }

    /**
     * Parses the data from an array and saves them to the database as a companion
     * @param array $data An array containing the data
     * @return bool Returns whether the companion was changed/saved or not
     */
    public function importCompanion(array $data): bool
    {
        $updated = false;
        $companion = Companion::where('id', $data['uniqueName'])->first();

        // Handle blacklist
        if (($data['productCategory'] ?? 'none') === 'Pistols') {
            if ($companion === null) {
                return false;
            }

            $companion->delete();
            return true;
        }

        // Create new
        if ($companion === null) {
            $companion = new Companion();
            $companion->id = $data['uniqueName'];
            $updated = true;
        }

        // Update contents
        $companion->name = $data['name'];
        $companion->description = $data['description'] ?? '';
        $companion->type = $data['category'];
        $companion->pet_type = $data['productCategory'];
        $companion->wiki_url = $data['wikiaUrl'] ?? null;
        $companion->prime = $data['isPrime'];
        $companion->icon = isset($data['imageName']) ? $this->importAsset($data['imageName'], 'companion') : null;
        $companion->save();

        // Update components
        if (isset($data['components'])) {
            $componentsUpdates = false;
            $occurrences = [];
            foreach ($data['components'] as $component) {
                $occurrences[$component['uniqueName']] = ($occurrences[$component['uniqueName']] ?? 0) + 1;
                $componentUpdated = $this->importComponent($component, $companion, 'companion', $occurrences[$component['uniqueName']]);
                $componentsUpdates = $updated || $componentUpdated;
            }

            // Delete if the blueprint only has 1 component
            if (Component::where('blueprint_id', $companion->id)->count() === 1) {
                Component::where('blueprint_id', $companion->id)->delete();
            } else {
                $updated = $updated || $componentsUpdates;
            }
        }

        return $updated ? true : $companion->wasChanged();
    }

    /**
     * Import a component from a blueprint
     * @param array $data The component data
     * @param ArsenalDataModel $blueprint The blueprint
     * @param string $type The type of blueprint
     * @return bool Whether or not the component was updated
     */
    public function importComponent(array $data, ArsenalDataModel $blueprint, string $type, int $occurrence = 1): bool
    {
        $updated = false;
        $effectiveId = $data['uniqueName'] . ($occurrence > 1 ? '-' . $occurrence : '');
        $component = Component::where('id', $effectiveId)->where('blueprint_id', $blueprint->id)->first();

        // Handle blacklist
        if (
            ($data['type'] ?? 'none') === 'Resource' ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Items/Gems/') ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Gameplay/') ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Items/MiscItems/') ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Items/RailjackMiscItems/') ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Items/Research/') ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Items/Fish')
        ) {
            if ($component === null) {
                return false;
            }

            $component->delete();
            return true;
        }

        // Create new
        if ($component === null) {
            $component = new Component();
            $component->id = $effectiveId;
            $component->blueprint_id = $blueprint->id;
            $component->type = $type;
            $updated = true;
        }

        // Update content
        $component->name = ucfirst(sprintf('%s %s', $blueprint->name, $data['name']));
        $component->amount = $data['itemCount'];
        $component->icon = isset($data['imageName']) ? $this->importAsset($data['imageName'], 'component') : null;
        $component->save();

        return $updated ? true : $component->wasChanged();
    }

    /**
     * Import an asset if it does not exist yet
     * @param string $assetName The name of the asset
     * @param string $category The asset category
     * @return string
     */
    protected function importAsset(string $assetName, string $category): string {
        $imgUrl = sprintf('%s/%s', self::imageUrl, $assetName);
        $imgPath = sprintf('modules/arsenal/%s/%s', $category, $assetName);
        if (Storage::missing($imgPath)) {
            $contents = file_get_contents($imgUrl);
            if (getimagesizefromstring($contents) !== false) {
                Storage::put($imgPath, $contents);
            }
        }

        return $imgPath;
    }

    /**
     * Parses the data from an array and saves them to the database as a nature
     * @param array $natureData An array containing the data of the nature
     * @return bool Returns whether the nature was changed/saved or not
     */
    public function importNature(array $natureData): bool
    {
        $new = false;
        $nature = Nature::where('nature', $natureData['nature'])->first();
        if ($nature === null) {
            $nature = new Nature;
            $nature->nature = $natureData['nature'];
            $new = true;
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

        if ($new === true) {
            return true;
        }

        return $nature->wasChanged();
    }

    /**
     * Parses the data from an array and saves them to the database as a move
     * @param array $moveData An array containing the data of the move
     * @return bool Returns whether the move was changed/saved or not
     */
    public function importMove(array $moveData): bool
    {
        $new = false;
        $code = $this->formatCode($moveData['move']);
        $move = Move::where('move', $code)->first();
        if ($move === null) {
            $move = new Move;
            $move->move = $code;
            $new = true;
        }

        $move->name = $moveData['name'][0]['name'];
        $move->power = $moveData['power'];
        $move->accuracy = $moveData['accuracy'];
        $move->priority = $moveData['priority'];
        $move->type = $moveData['type']['type'];
        $move->move_type = $moveData['move_type']['move_type'];
        $move->description = ($moveData['description'] === null) ? $moveData['description'][0]['description'] : "";
        $move->save();

        if ($new === true) {
            return true;
        }

        return $move->wasChanged();
    }

    /**
     * Parses the data from an array and saves them to the database as a ability
     * @param array $abilityData An array containing the data of the ability
     * @return bool Returns whether the ability was changed/saved or not
     */
    public function importAbility(array $abilityData): bool
    {
        $new = false;
        $code = $this->formatCode($abilityData['ability']);
        $ability = Ability::where('ability', $code)->first();
        if ($ability === null) {
            $ability = new Ability;
            $ability->ability = $code;
            $new = true;
        }

        $ability->name = $abilityData['name'][0]['name'];
        $ability->description = ($abilityData['description'] !== []) ? $abilityData['description'][0]['description'] : "";
        $ability->save();

        if ($new === true) {
            return true;
        }

        return $ability->wasChanged();
    }

    /**
     * Parses the data from an array and saves them to the database as a pokeball
     * @param array $pokeballData An array containing the data of the pokeball
     * @return bool Returns whether the pokeball changed/saved or not
     */
    public function importPokeball(array $pokeballData): bool
    {
        $new = false;
        $code = str_replace('-ball', '', $pokeballData['pokeball']);
        $code = $this->formatCode($code) . '-ball';

        $pokeball = Pokeball::where('pokeball', $code)->first();
        if ($pokeball === null) {
            $pokeball = new Pokeball;
            $pokeball->pokeball = $code;
            $new = true;
        }

        $pokeball->sprite = $this->importSprite(
            $pokeballData['sprite_string'][0]['sprite_string']['default'],
            'pokeball',
        );

        $pokeball->name = $pokeballData['name'][0]['name'];
        $pokeball->save();

        if ($new === true) {
            return true;
        }

        return $pokeball->wasChanged();
    }

    /**
     * Parses the data from an array and saves them to the database as a pokemon
     * @param array $pokemonData An array containing the data of the pokemon
     * @return bool Returns whether the pokemon changed/saved or not
     */
    public function importPokemon(array $pokemonData): bool
    {
        $new = false;
        $code = $this->formatCode($pokemonData['pokemon']);
        $pokemon = Pokemon::where('pokemon', $code)->first();
        if ($pokemon === null) {
            $pokemon = new Pokemon;
            $pokemon->pokemon = $code;
            $new = true;
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

        if ($new === true) {
            return true;
        }

        return $pokemon->wasChanged();
    }

    /**
     * Gets the config JSON containing a list of games
     * @return array Returns an array containing the games
     */
    public function getGamesJson(): array {
        return Storage::json('/config/PKSanc/games.json');
    }

    /**
     * Saves a game to the database
     * @param string $name The display name of the game
     * @param string $code The internal code used by PKSaveExtractor for the game
     * @return bool Returns whether the pokemon changed/saved or not
     */
    public function importGame(string $name, string $code): bool {
        $code = strtolower($code);
        $game = Game::where('game', $code)->first();
        if ($game !== null) {
            return false;
        }

        $game = new Game();
        $game->game = $code;
        $game->name = $name;
        $game->save();

        return true;
    }

    /**
     * Downloads a sprite from the PokeAPI github and saves it
     * @param ?string $spriteString The path to the sprite on the PokeAPI github. When null means the sprite does not exist.
     * @param string $spriteType What kind of sprite it is, eg. pokeball or pokemon
     * @param ?string $fileName The filename the file should be saved with, if null then the original name is used
     * @return ?string Returns the relative path to the sprite, if any
     */
    private function importSprite(?string $spriteString, string $spriteType, ?string $fileName = null): ?string
    {
        if ($fileName === null) {
            $arr = explode('/', $spriteString);
            $fileName = end($arr);
        }

        if ($spriteString !== null) {
            $spriteUrl = str_replace('/media/', 'https://raw.githubusercontent.com/PokeAPI/sprites/master/', $spriteString);
            $spritePath = sprintf('modules/pksanc/%s/%s', $spriteType, $fileName);
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

    /**
     * Formats the text in a display format
     * @param string $oldText Text to be formatted
     * @return string The formatted string
     */
    private function formatText(string $oldText): string
    {
        $text = str_replace('-', ' ', $oldText);
        return ucfirst($text);
    }

    /**
     * Formats the text in as an internal code
     * @param string $oldCode Text to be formatted
     * @return string The formatted string
     */
    private function formatCode(string $oldCode): string
    {
        $code = str_replace('-', '', $oldCode);
        return strtolower($code);
    }
}
