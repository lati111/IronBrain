<?php

namespace App\Service\PKSanc;

use App\Enum\PKSanc\PokemonTypes;
use App\Models\PKSanc\Ability;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\Move;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\Pokeball;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Type;
use App\Service\Traits\DownloadsImages;
use App\Service\Traits\PreloadsModels;
use Closure;
use Illuminate\Support\Facades\Storage;

class ImportService
{
    use DownloadsImages, PreloadsModels;

    private const string spriteUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/';
    private const string pokemonSpriteUrl = self::spriteUrl . 'sprites/pokemon/';

    /** @var array<string, true> The codes of the pokemon imported during this run */
    private array $importedPokemon = [];

    /**
     * Sets the database connection used by the importer
     * @param string $connection The name of the connection
     */
    public function setConnection(string $connection): void {
        \Config::set('database.connections.mysql.database', $connection);
        \DB::purge('mysql');
        $this->clearPreloaded();
    }

    /**
     * Parses the data from an array and saves them to the database as a type
     * @param array $typeData An array containing the data of the type
     * @return bool Returns whether the type was changed/saved or not
     */
    public function importType(array $typeData): bool
    {
        $type = $this->preloaded(Type::class, 'type')->get($typeData['type']);
        if ($type === null) {
            $type = new Type;
            $type->type = $typeData['type'];
            $this->preloaded(Type::class, 'type')->put($type->type, $type);
        }

        $type->name = $typeData['name'][0]['name'];
        $type->save();

        return $type->wasRecentlyCreated || $type->wasChanged();
    }

    /**
     * Parses the data from an array and saves them to the database as a nature
     * @param array $natureData An array containing the data of the nature
     * @return bool Returns whether the nature was changed/saved or not
     */
    public function importNature(array $natureData): bool
    {
        $nature = $this->preloaded(Nature::class, 'nature')->get($natureData['nature']);
        if ($nature === null) {
            $nature = new Nature;
            $nature->nature = $natureData['nature'];
            $this->preloaded(Nature::class, 'nature')->put($nature->nature, $nature);
        }

        $nature->name = $natureData['name'][0]['name'];

        // Stat ids as used by PokeAPI
        $modifiers = [
            'atk_modifier' => 2,
            'def_modifier' => 3,
            'spa_modifier' => 4,
            'spd_modifier' => 5,
            'spe_modifier' => 6,
        ];

        foreach ($modifiers as $column => $statId) {
            $modifier = 1.00;
            if ($natureData['increased_stat'] === $statId) {
                $modifier = 1.10;
            } else if ($natureData['decreased_stat'] === $statId) {
                $modifier = 0.90;
            }

            // Only assign changed values, as the database returns the modifiers as strings
            if ($nature->$column != $modifier) {
                $nature->$column = $modifier;
            }
        }

        $nature->save();

        return $nature->wasRecentlyCreated || $nature->wasChanged();
    }

    /**
     * Parses the data from an array and saves them to the database as a move
     * @param array $moveData An array containing the data of the move
     * @return bool Returns whether the move was changed/saved or not
     */
    public function importMove(array $moveData): bool
    {
        $code = $this->formatCode($moveData['move']);
        $move = $this->preloaded(Move::class, 'move')->get($code);
        if ($move === null) {
            $move = new Move;
            $move->move = $code;
            $this->preloaded(Move::class, 'move')->put($code, $move);
        }

        $move->name = $moveData['name'][0]['name'];
        $move->power = $moveData['power'];
        $move->accuracy = $moveData['accuracy'];
        $move->priority = $moveData['priority'];
        $move->type = $moveData['type']['type'];
        $move->move_type = $moveData['move_type']['move_type'];
        $move->description = $moveData['description'][0]['description'] ?? '';
        $move->save();

        return $move->wasRecentlyCreated || $move->wasChanged();
    }

    /**
     * Parses the data from an array and saves them to the database as a ability
     * @param array $abilityData An array containing the data of the ability
     * @return bool Returns whether the ability was changed/saved or not
     */
    public function importAbility(array $abilityData): bool
    {
        $code = $this->formatCode($abilityData['ability']);
        $ability = $this->preloaded(Ability::class, 'ability')->get($code);
        if ($ability === null) {
            $ability = new Ability;
            $ability->ability = $code;
            $this->preloaded(Ability::class, 'ability')->put($code, $ability);
        }

        $ability->name = $abilityData['name'][0]['name'];
        $ability->description = $abilityData['description'][0]['description'] ?? '';
        $ability->save();

        return $ability->wasRecentlyCreated || $ability->wasChanged();
    }

    /**
     * Downloads the sprites of a list of pokeballs in parallel, to speed up importing them
     * @param array $pokeballCollection The pokeballs as returned by PokeAPI
     * @param ?Closure(int $done, int $total): void $onProgress Called as the sprites are downloaded
     */
    public function prefetchPokeballSprites(array $pokeballCollection, ?Closure $onProgress = null): void
    {
        $urls = [];
        foreach ($pokeballCollection as $pokeballData) {
            $spriteString = $pokeballData['sprite_string'][0]['sprite_string']['default'] ?? null;
            if ($spriteString !== null) {
                $urls[sprintf('modules/pksanc/pokeball/%s', basename($spriteString))] = $this->toSpriteUrl($spriteString);
            }
        }

        $this->prefetchImages($urls, $onProgress);
    }

    /**
     * Parses the data from an array and saves them to the database as a pokeball
     * @param array $pokeballData An array containing the data of the pokeball
     * @return bool Returns whether the pokeball changed/saved or not
     */
    public function importPokeball(array $pokeballData): bool
    {
        $code = str_replace('-ball', '', $pokeballData['pokeball']);
        $code = $this->formatCode($code) . '-ball';

        $pokeball = $this->preloaded(Pokeball::class, 'pokeball')->get($code);
        if ($pokeball === null) {
            $pokeball = new Pokeball;
            $pokeball->pokeball = $code;
            $this->preloaded(Pokeball::class, 'pokeball')->put($code, $pokeball);
        }

        // The sprite column is not nullable, so keep the previous sprite if a new one could not be imported
        $pokeball->sprite = $this->importSprite(
            $pokeballData['sprite_string'][0]['sprite_string']['default'] ?? null,
            'pokeball',
        ) ?? $pokeball->sprite ?? '';

        $pokeball->name = $pokeballData['name'][0]['name'];
        $pokeball->save();

        return $pokeball->wasRecentlyCreated || $pokeball->wasChanged();
    }

    /**
     * Downloads the most likely sprites of a list of pokemon in parallel, to speed up importing them
     * @param array $pokemonCollection The pokemon as returned by PokeAPI
     * @param ?Closure(int $done, int $total): void $onProgress Called as the sprites are downloaded
     */
    public function prefetchPokemonSprites(array $pokemonCollection, ?Closure $onProgress = null): void
    {
        $urls = [];
        foreach ($pokemonCollection as $pokemonData) {
            $code = $this->formatCode($pokemonData['pokemon']);
            $sprites = $pokemonData['sprites'][0]['sprites'] ?? [];
            $default = isset($sprites['front_default']) ? $this->toSpriteUrl($sprites['front_default']) : null;

            $variants = [
                'shiny' => ['shiny/', 'front_shiny'],
                'female' => ['female/', 'front_female'],
                'shiny_female' => ['shiny/female/', 'front_shiny_female'],
            ];

            $urls[sprintf('modules/pksanc/pokemon/%s_default.png', $code)] = $default;
            foreach ($variants as $variant => [$directory, $key]) {
                if ($variant !== 'shiny' && !isset($sprites[$key])) {
                    continue;
                }

                $urls[sprintf('modules/pksanc/pokemon/%s_%s.png', $code, $variant)] = ($default !== null && str_starts_with($default, self::pokemonSpriteUrl))
                    ? self::pokemonSpriteUrl . $directory . basename($default)
                    : (isset($sprites[$key]) ? $this->toSpriteUrl($sprites[$key]) : null);
            }
        }

        $this->prefetchImages($urls, $onProgress);
    }

    /**
     * Parses the data from an array and saves them to the database as a pokemon
     * @param array $pokemonData An array containing the data of the pokemon
     * @return bool Returns whether the pokemon changed/saved or not
     */
    public function importPokemon(array $pokemonData): bool
    {
        $code = $this->formatCode($pokemonData['pokemon']);
        $this->importedPokemon[$code] = true;

        $pokemon = $this->preloaded(Pokemon::class, 'pokemon')->get($code);
        if ($pokemon === null) {
            $pokemon = new Pokemon;
            $pokemon->pokemon = $code;
            $this->preloaded(Pokemon::class, 'pokemon')->put($code, $pokemon);
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

        $this->importPokemonSprites($pokemon, $pokemonData);

        $pokemon->pokedex_id = $pokemonData['pokedex_id'];
        $pokemon->generation = (isset($pokemonData['generation'][0])) ? $pokemonData['generation'][0]['generation'] : $pokemonData['generation_backup']['generation'];
        $pokemon->pokemon_type = PokemonTypes::FORM;
        $pokemon->save();

        return $pokemon->wasRecentlyCreated || $pokemon->wasChanged();
    }

    /**
     * Deletes the pokemon that were not part of this import, unless a user has stored one
     * @return int Returns the amount of pokemon deleted
     */
    public function prunePokemon(): int
    {
        // Never prune when nothing was imported, as that would delete every pokemon
        if ($this->importedPokemon === []) {
            return 0;
        }

        $stale = Pokemon::whereNotIn('pokemon', array_keys($this->importedPokemon))
            ->whereNotIn('pokemon', StoredPokemon::select('pokemon'))
            ->pluck('pokemon');

        Pokemon::whereIn('pokemon', $stale)->delete();
        foreach ($stale as $code) {
            $this->preloaded(Pokemon::class, 'pokemon')->forget($code);
        }

        return $stale->count();
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
     * @return bool Returns whether the game changed/saved or not
     */
    public function importGame(string $name, string $code): bool {
        $code = strtolower($code);
        $game = $this->preloaded(Game::class, 'game')->get($code);
        if ($game === null) {
            $game = new Game();
            $game->game = $code;
            $this->preloaded(Game::class, 'game')->put($code, $game);
        }

        $game->name = $name;
        $game->save();

        return $game->wasRecentlyCreated || $game->wasChanged();
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
        if ($spriteString === null) {
            return null;
        }

        $fileName ??= basename($spriteString);
        $spritePath = sprintf('modules/pksanc/%s/%s', $spriteType, $fileName);

        return $this->downloadImage($this->toSpriteUrl($spriteString), $spritePath) ? $fileName : null;
    }

    /**
     * Downloads all sprites for a pokemon form and sets them on the pokemon.
     * PokeAPI's form sprite urls are only reliable for the default sprite, so the file name of the default sprite
     * is used to find every other variant, with PokeAPI's own url as a fallback.
     * @param Pokemon $pokemon The pokemon to set the sprites on
     * @param array $pokemonData An array containing the data of the pokemon
     */
    private function importPokemonSprites(Pokemon $pokemon, array $pokemonData): void
    {
        $sprites = $pokemonData['sprites'][0]['sprites'] ?? [];
        $apiUrl = fn (string $key): ?string => isset($sprites[$key]) ? $this->toSpriteUrl($sprites[$key]) : null;

        // Only prompt when PokeAPI claims a sprite exists, as forms without one fall back to the species sprite
        $defaultUrl = $this->downloadFirstImage(
            [$apiUrl('front_default'), ...$this->getFallbackSpriteUrls($pokemonData)],
            sprintf('modules/pksanc/pokemon/%s_default.png', $pokemon->pokemon),
            $apiUrl('front_default') !== null,
        );
        $pokemon->sprite = ($defaultUrl !== null) ? sprintf('%s_default.png', $pokemon->pokemon) : null;

        // The file name of the default sprite in the sprites repository, eg. "10094.png"
        $spriteFile = null;
        if ($defaultUrl !== null && str_starts_with($defaultUrl, self::pokemonSpriteUrl)) {
            $spriteFile = substr($defaultUrl, strlen(self::pokemonSpriteUrl));
        }

        $variantUrl = fn (string $directory): ?string => ($spriteFile !== null) ? self::pokemonSpriteUrl . $directory . $spriteFile : null;

        $pokemon->sprite_shiny = $this->importPokemonSprite(
            [$variantUrl('shiny/'), $apiUrl('front_shiny')],
            sprintf('%s_shiny.png', $pokemon->pokemon),
        );

        // Female sprites only exist for pokemon with gender differences, which PokeAPI reports reliably
        $pokemon->sprite_female = ($apiUrl('front_female') !== null) ? $this->importPokemonSprite(
            [$variantUrl('female/'), $apiUrl('front_female')],
            sprintf('%s_female.png', $pokemon->pokemon),
        ) : null;

        $pokemon->sprite_female_shiny = ($apiUrl('front_shiny_female') !== null) ? $this->importPokemonSprite(
            [$variantUrl('shiny/female/'), $apiUrl('front_shiny_female')],
            sprintf('%s_shiny_female.png', $pokemon->pokemon),
        ) : null;
    }

    /**
     * Downloads a pokemon sprite from the first url that has one
     * @param array<?string> $urls The urls to try, in order of preference
     * @param string $fileName The filename the sprite should be saved with
     * @return ?string Returns the filename of the sprite, or null if no sprite could be imported
     */
    private function importPokemonSprite(array $urls, string $fileName): ?string
    {
        $path = sprintf('modules/pksanc/pokemon/%s', $fileName);
        return ($this->downloadFirstImage($urls, $path) !== null) ? $fileName : null;
    }

    /**
     * Gets the urls a form's default sprite may be stored under when PokeAPI does not provide a working one
     * @param array $pokemonData An array containing the data of the pokemon
     * @return string[] Returns the urls, in order of preference
     */
    private function getFallbackSpriteUrls(array $pokemonData): array
    {
        $speciesId = $pokemonData['details']['species_id'];
        $pokemonId = $pokemonData['pokedex_id'];

        $urls = [];
        if ($pokemonData['form_name'] !== '') {
            $urls[] = sprintf('%s%d-%s.png', self::pokemonSpriteUrl, $speciesId, $pokemonData['form_name']);
        }

        // Forms with their own pokemon entry (eg. regional forms) are stored under that pokemon's id
        if ($pokemonId !== $speciesId) {
            $urls[] = sprintf('%s%d.png', self::pokemonSpriteUrl, $pokemonId);
        }

        return $urls;
    }

    /**
     * Converts a sprite string from PokeAPI to a full url
     * @param string $spriteString The url or legacy "/media/" path of the sprite
     * @return string Returns the url to the sprite
     */
    private function toSpriteUrl(string $spriteString): string
    {
        return str_replace('/media/', self::spriteUrl, $spriteString);
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
