<?php

namespace App\Http\Controllers\Projects\PKSanc;

use App\Enum\PKSanc\PKSancStrings;
use App\Enum\PKSanc\StoragePaths;
use App\Http\Controllers\Controller;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Service\PKSanc\DepositService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PKSancController extends Controller
{
    private DepositService $depositService;

    public function __construct(DepositService $depositService)
    {
        $this->depositService = $depositService;
    }

    public function showOverview()
    {
        return view('project.pksanc.home', $this->getBaseVariables());
    }

    public function showDeposit()
    {
        return view('project.pksanc.deposit', array_merge($this->getBaseVariables(), [
            'gamesCollection' => Game::all(),
        ]));
    }

    public function stageDepositAttempt(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:4|max:255',
            'csv' => 'required|mimes:csv,txt|max:480',
            'game' => 'required|exists:pksanc__game,game'
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->all())
                ->withErrors($validator);
        }

        $game = Game::where('game', $request->game)->first();
        $date = Carbon::now()->unix();
        $filename = sprintf('%s (%s).csv', $game->name, $date);

        $path = sprintf(
            StoragePaths::csv,
            $user->uuid,
            $request->name,
        );

        Storage::putFileAs($path, $request->file('csv'), $filename);

        $csv = new ImportCsv();
        $csv->csv = $filename;
        $csv->game = $game->game;
        $csv->name = $request->name;
        $csv->uploader_uuid = $user->uuid;
        $csv->version = 1;
        $csv->save();

        $this->depositService->stageImport($csv);

        return redirect(route('pksanc.deposit.stage.show', $csv->uuid));
    }

    public function showDepositAttempt(string $importUuid)
    {
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            //TODO add error screen
            dd(sprintf('No import csv matching the uuid %s found', $importUuid));
        }

        return view('project.pksanc.stage-deposit', array_merge($this->getBaseVariables(), [
            'importUuid' => $importUuid,
        ]));
    }

    public function depositConfirm(string $importUuid)
    {
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            //TODO add error screen
            dd(sprintf('No import csv matching the uuid %s found', $importUuid));
        }

        foreach($csv->getPokemon as $pokemon) {
            $this->depositService->confirmStaging($pokemon->getStaging());
        }

        return redirect(route('pksanc.home.show'))->with('message', PKSancStrings::DEPOSIT_SUCCESS);
    }

    public function depositCancel(string $importUuid)
    {
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            //TODO add error screen
            dd(sprintf('No import csv matching the uuid %s found', $importUuid));
        }

        foreach($csv->getPokemon as $pokemon) {
            $pokemon->delete();
        }

        return redirect(route('pksanc.home.show'))->with('message', PKSancStrings::DEPOSIT_CANCELLED);
    }
}
