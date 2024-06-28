<?php

namespace App\Http\Api\Modules\PKSanc;

use App\Enum\PKSanc\PokedexMarkings;
use App\Http\Api\AbstractApi;
use App\Models\PKSanc\PokedexMarking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PokedexApi extends AbstractApi
{
    /**
     * Mark a new pokedex entry
     * @param Request $request The request parameters as passed by laravel
     * @return JsonResponse True or an error in json format
     */
    public function setPokedexMarking(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pokedex_id' => 'required|integer',
            'form_index' => 'required|integer',
            'marking' => ['required', 'string', Rule::in(PokedexMarkings::list)],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $user = Auth::user();

        $markingExists = PokedexMarking::where('pokedex_id', $request->get('pokedex_id'))
            ->where('form_index', $request->get('form_index'))
            ->where('marking', $request->get('marking'))
            ->where('user_uuid', $user->uuid)
            ->exists();

        if ($markingExists) {
            return response()->json(true, Response::HTTP_ALREADY_REPORTED);
        }

        $marking = new PokedexMarking();
        $marking->pokedex_id = $request->get('pokedex_id');
        $marking->form_index = $request->get('form_index');
        $marking->marking = $request->get('marking');
        $marking->user_uuid = $user->uuid;
        $marking->save();

        return response()->json(true, Response::HTTP_CREATED);
    }

    /**
     * Unmark a new pokedex entry
     * @param Request $request The request parameters as passed by laravel
     * @return JsonResponse True or an error in json format
     */
    public function removePokedexMarking(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pokedex_id' => 'required|integer',
            'form_index' => 'required|integer',
            'marking' => ['required', 'string', Rule::in(PokedexMarkings::list)],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $user = Auth::user();

        $marking = PokedexMarking::where('pokedex_id', $request->get('pokedex_id'))
            ->where('form_index', $request->get('form_index'))
            ->where('marking', $request->get('marking'))
            ->where('user_uuid', $user->uuid)
            ->first();

        if ($marking === null) {
            return response()->json(true, Response::HTTP_ALREADY_REPORTED);
        }

        $marking->delete();

        return response()->json(true, Response::HTTP_OK);
    }
}
