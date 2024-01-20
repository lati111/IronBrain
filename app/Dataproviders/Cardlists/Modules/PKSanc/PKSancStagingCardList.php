<?php
namespace App\Dataproviders\Cardlists\Modules\PKSanc;

use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class PKSancStagingCardList extends AbstractPKSancOverviewCardList
{
    /** {@inheritDoc} */
    public function data(Request $request): JsonResponse
    {
        $importUuid = $request->route()->parameter('import_uuid');
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            return response()->json(sprintf('No import csv matching the uuid %s found', $importUuid), ResponseAlias::HTTP_NOT_FOUND);
        }

        $pokemonCollection = $csv->Pokemon()
            ->where('validated_at', null)
            ->where('owner_uuid', Auth::user()->uuid);
        $pokemonCollection = $this->applyTableFilters($request, $pokemonCollection, true)->get();

        $data = [];
        foreach ($pokemonCollection as $pokemon) {
            $data[] = $this->getCard($pokemon);
        }

        return response()->json($data, ResponseAlias::HTTP_OK);
    }

    /** {@inheritDoc} */
    public function count(Request $request): JsonResponse
    {
        $importUuid = $request->route()->parameter('import_uuid');
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            return response()->json(sprintf('No import csv matching the uuid %s found', $importUuid), ResponseAlias::HTTP_NOT_FOUND);
        }

        $pokemonCollection = $csv->Pokemon()
            ->where('validated_at', null)
            ->where('owner_uuid', Auth::user()->uuid);
        $count =  $this->getCount($request, $pokemonCollection);

        return response()->json($count, ResponseAlias::HTTP_OK);
    }
}
