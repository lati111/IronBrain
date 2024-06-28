<?php

namespace App\Http\Controllers\Modules\PKSanc;

use App\Enum\PKSanc\PKSancStrings;
use App\Enum\PKSanc\StoragePaths;
use App\Http\Controllers\Controller;
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

class PKSancDepositController extends Controller
{
    private DepositService $depositService;

    public function __construct(DepositService $depositService)
    {
        $this->depositService = $depositService;
    }

    /**
     * Shows the PKSanc depositing page
     * @return View Returns a View of the page
     */
    public function showDeposit(): View
    {
        return view('modules.pksanc.deposit', array_merge($this->getBaseVariables(), [
            'gamesCollection' => Game::all(),
        ]));
    }

    /**
     * Show a staging attempt
     * @return View Returns a View of the page
     */
    public function showDepositAttempt(string $importUuid): View
    {
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            //TODO add error screen
            dd(sprintf('No import csv matching the uuid %s found', $importUuid));
        }

        return view('modules.pksanc.stage-deposit', array_merge($this->getBaseVariables(), [
            'importUuid' => $importUuid,
        ]));
    }

    /**
     * Confirm a staging attempt
     * @return RedirectResponse Returns a redirection response
     */
    public function depositConfirm(string $importUuid): RedirectResponse
    {
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            //TODO add error screen
            dd(sprintf('No import csv matching the uuid %s found', $importUuid));
        }

        foreach($csv->Pokemon()->get() as $pokemon) {
            $this->depositService->confirmStaging($pokemon->getStaging());
        }

        return redirect(route('pksanc.home.show'))->with('message', PKSancStrings::DEPOSIT_SUCCESS);
    }

    /**
     * Cancel a staging attempt
     * @return RedirectResponse Returns a redirection response
     */
    public function depositCancel(string $importUuid): RedirectResponse
    {
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            //TODO add error screen
            dd(sprintf('No import csv matching the uuid %s found', $importUuid));
        }

        foreach($csv->Pokemon()->get() as $pokemon) {
            $pokemon->delete();
        }

        return redirect(route('pksanc.home.show'))->with('message', PKSancStrings::DEPOSIT_CANCELLED);
    }
}
