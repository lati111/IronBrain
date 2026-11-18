<?php

namespace App\Http\Controllers\Modules\DnD\Compendium;

use App\Http\Controllers\Controller;
use App\Models\DnD\Compendium\Statblock;
use Illuminate\View\View;

class StatblockController extends Controller
{
    public function showStatblock(): View
    {
        return view('modules.dnd.compendium.test', $this->getBaseVariables());
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
