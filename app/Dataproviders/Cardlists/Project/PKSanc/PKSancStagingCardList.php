<?php
namespace App\Dataproviders\Cardlists\Project\PKSanc;

use App\Models\PKSanc\ImportCsv;
use Illuminate\Http\Request;

class PKSancStagingCardList extends PKSancOverviewCardList
{

    public function data(Request $request, string $importUuid)
    {
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            return response()->json(sprintf('No import csv matching the uuid %s found', $importUuid), 404);
        }

        $pokemonCollection = $csv->getPokemon();
        $pokemonCollection = $this->applyTableFilters($request, $pokemonCollection, false)->get();

        $data = [];
        foreach ($pokemonCollection as $pokemon) {
            $iconList = $this->getIconList($pokemon);

            $spriteBlock = $this->getSpriteBlock(
                $pokemon->getSprite(),
                $pokemon->Pokemon()->getName(),
                $pokemon->Pokemon()->pokemon,
                ($pokemon->nickname !== null) ? $pokemon->nickname : $pokemon->Pokemon()->species_name
            );

            $infoBlock = $this->getMinimalInfoBlock(
                $pokemon->Nature(),
                $pokemon->Ability(),
                $pokemon->HiddenPower(),
                $pokemon->level
            );

            $origin = $pokemon->Origin();
            $trainer = $origin->Trainer();
            $trainerBlock = $this->getMinimalTrainerBlock(
                $pokemon->Csv()->name,
                $origin->Game()->name,
                $origin->met_location,
                $trainer->name,
                $trainer->gender,
            );

            $data[] = [
                $iconList,
                $spriteBlock,
                $infoBlock,
                $trainerBlock
            ];
        }

        return response()->json($data, 200);
    }
}
