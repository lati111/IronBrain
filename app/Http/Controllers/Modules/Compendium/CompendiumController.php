<?php

namespace App\Http\Controllers\Modules\Compendium;

use App\Http\Controllers\Controller;
use App\Models\Compendium\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompendiumController extends Controller
{
    /**
     * Shows the docs overview page
     * @return View Returns a View of the page
     */
    public function campaigns(): View
    {
        return $this->view('modules.compendium.campaigns');
    }

    /**
     * Shows campaign management page
     * @return View|RedirectResponse Returns a View of the page, or a redirect to the overview
     */
    public function campaign(string $campaign_uuid): View|RedirectResponse
    {
        $campain = Campaign::find($campaign_uuid);
        if ($campain === null) {
            return redirect(route('compendium.campaigns'))->with(['error' => 'Invalid campaign.']);
        }

        return $this->view('modules.compendium.campaign', ['campaign' => $campain]);
    }
}
