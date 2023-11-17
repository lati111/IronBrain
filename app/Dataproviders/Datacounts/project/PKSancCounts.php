<?php
namespace App\Dataproviders\Datacounts\Project;

use App\Dataproviders\Datacounts\AbstractDatacount;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;


class PKSancCounts extends AbstractDatacount
{
    public function overviewCount(Request $request) {
        $pokemonCollection = StoredPokemon::select()
            ->where('validated_at', '!=', null)
            ->where('owner_uuid', Auth::user()->uuid);
        $count =  $this->getCount($request, $pokemonCollection, true);

        return response()->json($count, Response::HTTP_OK);
    }
}
