<?php

namespace App\Http\Api\Modules\PKSanc;

use App\Enum\PKSanc\StoragePaths;
use App\Http\Api\AbstractApi;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Service\PKSanc\DepositService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

class DepositApi extends AbstractApi
{
    private DepositService $depositService;

    public function __construct(DepositService $depositService)
    {
        $this->depositService = $depositService;
    }

    /**
     * Attempt to stage a deposit
     * @return View|RedirectResponse Returns a View of the page or a redirection response
     * @throws Throwable
     */
    public function stageDepositAttempt(Request $request): View|RedirectResponse
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
}
