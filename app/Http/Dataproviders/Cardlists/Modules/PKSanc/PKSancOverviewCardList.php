<?php
namespace App\Http\Dataproviders\Cardlists\Modules\PKSanc;

use App\Models\PKSanc\StoredPokemon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PKSancOverviewCardList extends AbstractPKSancOverviewCardList
{
    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        /** @var Builder $pokemonCollection */
        $pokemonCollection = StoredPokemon::select()
            ->where('validated_at', '!=', null)
            ->where(StoredPokemon::getTableName().'.owner_uuid', Auth::user()->uuid);

        return $this->applySelects($pokemonCollection);
    }
}
