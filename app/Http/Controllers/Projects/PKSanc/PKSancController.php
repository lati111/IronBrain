<?php

namespace App\Http\Controllers\Projects\PKSanc;

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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PKSancController extends Controller
{
    /**
     * Shows the PKSanc homepage
     * @return View Returns a View of the page
     */
    public function showOverview(): View
    {
        return view('project.pksanc.home', $this->getBaseVariables());
    }
}
