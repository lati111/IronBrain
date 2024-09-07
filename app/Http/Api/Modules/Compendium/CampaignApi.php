<?php

namespace App\Http\Api\Modules\Compendium;

use App\Enum\ErrorEnum;
use App\Enum\Modules\Compendium\ResponseStrings;
use App\Enum\PKSanc\PKSancStrings;
use App\Enum\PKSanc\StoragePaths;
use App\Http\Api\AbstractApi;
use App\Models\Compendium\Campaign;
use App\Models\Compendium\Player;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Service\PKSanc\DepositService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CampaignApi extends AbstractApi
{
    /**
     * Create a new campaign, and add the current user as the DM
     * @param Request $request The request parameters as passed by laravel
     * @return JsonResponse The newly created campaign or an error in json format
     */
    public function addCampaign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:1|max:128',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $campaign = new Campaign();
        $campaign->title = $request->title;
        $campaign->save();

        $dm = new Player();
        $dm->campaign_uuid = $campaign->uuid;
        $dm->user_uuid = Auth::user()->uuid;
        $dm->is_dm = true;
        $dm->save();

        return $this->respond(Response::HTTP_CREATED, ResponseStrings::CAMPAIGN_STARTED, $campaign);
    }
}
