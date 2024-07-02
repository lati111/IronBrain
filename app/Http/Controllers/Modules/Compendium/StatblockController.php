<?php

namespace App\Http\Controllers\Modules\Compendium;

use App\Http\Controllers\Controller;
use App\Models\Compendium\Action;
use App\Models\Compendium\CreatureTemplate;
use App\Models\Compendium\CreatureTemplateResistance;
use App\Models\Compendium\ResistanceModifier;
use App\Models\Compendium\Statblock;
use App\Models\Compendium\StatblockCreatureTemplate;
use Illuminate\View\View;

class StatblockController extends Controller
{
    public function showStatblock(): View
    {
        return view('modules.compendium.test', $this->getBaseVariables());
    }

    public function getData() {
        $data = Statblock::select()
            ->with([
                'actions',
                'resistances',
                'proficiencies',
                'statModifiers',
                'rollModifiers',
                'resources',
            ])->first();

        dd($data->toArray());
        return response()->json($data, 200);
    }
}
