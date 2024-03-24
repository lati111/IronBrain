<?php

namespace App\Service\PKSanc\CsvHydrator;

use App\Enum\PKSanc\ImportValidators;
use App\Models\PKSanc\ContestStats;
use App\Models\PKSanc\Moveset;
use App\Models\PKSanc\Origin;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\Stats;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Trainer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CsvHydratorV1 extends AbstractCsvHydrator
{
    protected function validate(array $data): bool
    {
        $validator = Validator::make($data, [
            'PID' => ImportValidators::PID,
            'Nickname' => ImportValidators::NICKNAME,
            'Species' => sprintf(ImportValidators::SPECIES, 'Form'),
            'Form' => ImportValidators::FORM_INDEX,
            'Ability' => ImportValidators::ABILITY,
            'Nature' => ImportValidators::NATURE,
            'Gender' => ImportValidators::POKEMON_GENDER,
            'Level' => ImportValidators::LEVEL,
            'Friendship' => ImportValidators::FRIENDSHIP,
            'Ball' => ImportValidators::POKEBALL,
            'Height' => ImportValidators::SIZE,
            'Weight' => ImportValidators::SIZE,
            'Is_shiny' => ImportValidators::BOOL,
            'HP_type' => ImportValidators::TYPE,
            'Beauty' => ImportValidators::CONTEST_STAT,
            'Cool' => ImportValidators::CONTEST_STAT,
            'Cute' => ImportValidators::CONTEST_STAT,
            'Sheen' => ImportValidators::CONTEST_STAT,
            'Smart' => ImportValidators::CONTEST_STAT,
            'Tough' => ImportValidators::CONTEST_STAT,
            'HP_IV' => ImportValidators::IV,
            'ATK_IV' => ImportValidators::IV,
            'DEF_IV' => ImportValidators::IV,
            'SPA_IV' => ImportValidators::IV,
            'SPD_IV' => ImportValidators::IV,
            'SPE_IV' => ImportValidators::IV,
            'HP_EV' => ImportValidators::EV,
            'ATK_EV' => ImportValidators::EV,
            'DEF_EV' => ImportValidators::EV,
            'SPA_EV' => ImportValidators::EV,
            'SPD_EV' => ImportValidators::EV,
            'SPE_EV' => ImportValidators::EV,
            'Move1' => ImportValidators::MOVE,
            'Move1_pp_ups' => ImportValidators::PP_UPS,
            'Move2' => ImportValidators::MOVE,
            'Move2_pp_ups' => ImportValidators::PP_UPS,
            'Move3' => ImportValidators::MOVE,
            'Move3_pp_ups' => ImportValidators::PP_UPS,
            'Move4' => ImportValidators::MOVE,
            'Move4_pp_ups' => ImportValidators::PP_UPS,
            'Was_egg' => ImportValidators::BOOL,
            'Met_date' => ImportValidators::DATE,
            'Met_location' => ImportValidators::LOCATION,
            'Met_game' => ImportValidators::GAME,
            'Met_level' => ImportValidators::LEVEL,
            'Tera_type' => ImportValidators::TYPE,
            'Is_alpha' => ImportValidators::BOOL,
            'Can_gigantamax' => ImportValidators::BOOL,
            'Dynamax_level' => ImportValidators::DYNAMAX_LEVEL,

            'Trainer_TID' => ImportValidators::TRAINER_ID,
            'Trainer_SID' => ImportValidators::TRAINER_ID,
            'Trainer_gender' => ImportValidators::TRAINER_GENDER,
            'Trainer_name' => ImportValidators::TRAINER_NAME,
            'Trainer_game' => ImportValidators::GAME,
            'Version' => Rule::in(['v1.2']),
        ]);

        if ($validator->fails()) {
            $this->handleErrors($validator);
            return false;
        }

        return true;
    }

    protected function import(array $data, int $line): StoredPokemon
    {
        $pokemon = new StoredPokemon;
        $trainer = $this->importTrainer($data);

        $pokemon = $this->importPokemon($pokemon, $data, $line);
        $this->importOrigin($data, $pokemon, $trainer);
        $this->importStats($data, $pokemon);
        $this->importContestStats($data, $pokemon);
        $this->importMoves($data, $pokemon);

        $this->stagePokemon($pokemon);

        return $pokemon;
    }

    private function importPokemon(StoredPokemon $pokemon, array $data, int $line): StoredPokemon
    {
        $species = Pokemon::where('form_index', $data['Form'])->where(function ($query) use ($data) {
            $query
                ->orWhere('pokemon', $data['Species'])
                ->orWhere('species', $data['Species']);
        })->first();
        if ($species === null) {
            //TODO create proper error screen
            dd($data);
        }

        $pokemon->PID = intval($data['PID']);
        $pokemon->nickname = $data['Nickname'];
        $pokemon->pokemon = $species->pokemon;
        $pokemon->ability = $data['Ability'];
        $pokemon->nature = $data['Nature'];
        $pokemon->gender = $data['Gender'];
        $pokemon->level = intval($data['Level']);
        $pokemon->friendship = intval($data['Friendship']);
        $pokemon->pokeball = $data['Ball'];
        $pokemon->height = intval($data['Height']);
        $pokemon->weight = intval($data['Weight']);
        $pokemon->hidden_power_type = $data['HP_type'];
        $pokemon->tera_type = $data['Tera_type'];
        $pokemon->is_shiny = $this->parseBool($data['Is_shiny']);
        $pokemon->is_alpha = $this->parseBool($data['Can_gigantamax']);
        $pokemon->can_gigantamax = $this->parseBool($data['Is_alpha']);
        $pokemon->has_n_sparkle = $this->parseBool($data['Has_n_sparkle']);
        $pokemon->dynamax_level = intval($data['Dynamax_level']);
        $pokemon->csv_uuid = $this->importCsv->uuid;
        $pokemon->csv_line = $line;
        $pokemon->owner_uuid = Auth::user()->uuid;
        $pokemon->save();

        return $pokemon;
    }

    private function importTrainer(array $data): Trainer
    {
        $trainer = $this->getTrainer(intval($data['Trainer_TID']), intval($data['Trainer_SID']), $data['Trainer_name']);
        $trainer->trainer_id = intval($data['Trainer_TID']);
        $trainer->secret_id = intval($data['Trainer_SID']);
        $trainer->name = $data['Trainer_name'];
        $trainer->gender = $data['Trainer_gender'];
        $trainer->game = $data['Trainer_game'];
        $trainer->owner_uuid = Auth::user()->uuid;
        $trainer->save();

        return $trainer;
    }

    private function importOrigin(array $data, StoredPokemon $pokemon, Trainer $trainer): Origin
    {
        $origin = Origin::where('pokemon_uuid', $pokemon->uuid)->first() ?? new Origin();
        $origin->pokemon_uuid = $pokemon->uuid;
        $origin->trainer_uuid = $trainer->uuid;
        $origin->game = $data['Met_game'];
        $origin->met_date = $this->parseDate($data['Met_date']);
        $origin->met_location = $this->parseLocation($data['Met_location']);
        $origin->met_level = intval($data['Met_level']);
        $origin->was_egg = $this->parseBool($data['Was_egg']);
        $origin->save();

        return $origin;
    }

    private function importStats(array $data, StoredPokemon $pokemon): Stats
    {
        $stats = Stats::where('pokemon_uuid', $pokemon->uuid)->first() ?? new Stats();
        $stats->pokemon_uuid = $pokemon->uuid;
        $stats->hp_iv = intval($data['HP_IV']);
        $stats->hp_ev = intval($data['HP_EV']);
        $stats->atk_iv = intval($data['ATK_IV']);
        $stats->atk_ev = intval($data['ATK_EV']);
        $stats->def_iv = intval($data['DEF_IV']);
        $stats->def_ev = intval($data['DEF_EV']);
        $stats->spa_iv = intval($data['SPA_IV']);
        $stats->spa_ev = intval($data['SPA_EV']);
        $stats->spd_iv = intval($data['SPD_IV']);
        $stats->spd_ev = intval($data['SPD_EV']);
        $stats->spe_iv = intval($data['SPE_IV']);
        $stats->spe_ev = intval($data['SPE_EV']);
        $stats->save();

        return $stats;
    }

    private function importContestStats(array $data, StoredPokemon $pokemon): ContestStats
    {
        $contestStats = ContestStats::where('pokemon_uuid', $pokemon->uuid)->first() ?? new ContestStats();
        $contestStats->pokemon_uuid = $pokemon->uuid;
        $contestStats->beauty = intval($data['Beauty']);
        $contestStats->cool = intval($data['Cool']);
        $contestStats->cute = intval($data['Cute']);
        $contestStats->smart = intval($data['Smart']);
        $contestStats->tough = intval($data['Tough']);
        $contestStats->sheen = intval($data['Sheen']);
        $contestStats->save();

        return $contestStats;
    }

    private function importMoves(array $data, StoredPokemon $pokemon): Moveset
    {
        $moveset = MoveSet::where('pokemon_uuid', $pokemon->uuid)->first() ?? new Moveset();
        $moveset->pokemon_uuid = $pokemon->uuid;
        $moveset->move1 = ($data['Move1'] !== 'none') ? $data['Move1'] : null;
        $moveset->move1_pp_up = $data['Move1_pp_ups'];
        $moveset->move2 = ($data['Move2'] !== 'none') ? $data['Move2'] : null;
        $moveset->move2_pp_up = $data['Move2_pp_ups'];
        $moveset->move3 = ($data['Move3'] !== 'none') ? $data['Move3'] : null;
        $moveset->move3_pp_up = $data['Move3_pp_ups'];
        $moveset->move4 = ($data['Move4'] !== 'none') ? $data['Move4'] : null;
        $moveset->move4_pp_up = $data['Move4_pp_ups'];
        $moveset->save();

        return $moveset;
    }

    private function parseDate(string $date): string
    {
        $dates = explode('-', $date);
        $date = sprintf('%s-%s-%s', $dates[0], $dates[1], $dates[2]);
        return $date;
    }

    private function parseLocation(?string $location): string
    {
        if ($location === null) {
            return '';
        }

        $location = str_replace('_', ' ', $location);
        $location = ucfirst($location);
        return $location;
    }
}
