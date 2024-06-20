<?php
namespace App\Dataproviders\Cardlists\Modules\PKSanc;

use App\Exceptions\IronBrainException;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class PKSancStagingCardList extends AbstractPKSancOverviewCardList
{
    /**
     * { @inheritdoc }
     * @throws IronBrainException
     */
    protected function getContent(Request $request): Builder
    {
        $importUuid = $request->route()->parameter('import_uuid');
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            throw new IronBrainException(
                sprintf('No import csv matching the uuid %s found', $importUuid),
                'Import csv could not be loaded',
                ResponseAlias::HTTP_NOT_FOUND
            );
        }

        /** @var Builder $pokemonCollection */
        $pokemonCollection = $csv->Pokemon()
            ->where('validated_at', null)
            ->where(StoredPokemon::getTableName().'.owner_uuid', Auth::user()->uuid)
            ->getQuery();

        return $this->applySelects($pokemonCollection);
    }
}
