<?php

namespace App\Http\Controllers\Modules\PKSanc;

use App\Http\Controllers\Controller;
use App\Models\PKSanc\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PKSancContributionController extends Controller
{
    /**
     * Adds a new romhack
     * @param Request $request The request parameters as passed by laravel
     * @return JsonResponse The newly created romhack or an error in json format
     */
    public function addRomhack(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'original_game' => sprintf('required|string|exists:%s,game', Game::getTableName()),
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $index = 1;
        $code = str_replace(' ', '_', strtolower($request->get('name')));
        $original = Game::where('game', $code)->doesntExist();
        while ($original === false) {
            if (Game::where('game', $code.'_'.$index)->doesntExist()) {
                $original = true;
                $code = $code.'_'.$index;
            } else {
                $index++;
            }
        }

        $romhack = new Game();
        $romhack->game = $code;
        $romhack->name = $request->get('name');
        $romhack->original_game = $request->get('original_game');
        $romhack->is_romhack = true;
        $romhack->save();

        return response()->json($romhack, Response::HTTP_CREATED);
    }
}
