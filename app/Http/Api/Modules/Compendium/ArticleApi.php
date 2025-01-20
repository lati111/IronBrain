<?php

namespace App\Http\Api\Modules\Compendium;

use App\Enum\ErrorEnum;
use App\Enum\GenericStringEnum;
use App\Enum\Modules\Compendium\ResponseStrings;
use App\Enum\PKSanc\PKSancStrings;
use App\Enum\PKSanc\StoragePaths;
use App\Http\Api\AbstractApi;
use App\Models\Compendium\Campaign;
use App\Models\Compendium\Docs\AbstractArticle;
use App\Models\Compendium\Docs\Article;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ArticleApi extends AbstractCompendiumDocsApi
{
    /**
     * Create a new article and it's extension
     * @param Request $request The request parameters as passed by laravel
     * @return JsonResponse The newly created article or an error in json format
     */
    public function addArticle(Request $request, string $campaign_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:1|max:128',
            'type' => ['required', 'string', Rule::in(Article::TYPE_KEYS)],
            'dm_access' => 'nullable|boolean',
            'private' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        // Get campaign
        $campaign = Campaign::find($campaign_uuid);
        if ($campaign === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, ResponseStrings::CAMPAIGN_NOT_FOUND);
        }

        // Get player
        $player = $campaign->findPlayerByUser(Auth::user()->uuid);
        if ($player === null) {
            return $this->respond(Response::HTTP_FORBIDDEN, ResponseStrings::CAMPAIGN_NO_ACCESS);
        }

        // Create article
        $article = new Article();
        $article->name = $request->get('name');
        $article->player_uuid = $player->uuid;
        $article->campaign_uuid = $campaign->uuid;
        $article->dm_access = $request->get('dm_access', true);
        $article->private = $request->get('private', true);
        $article->save();

        // Create article extension
        $type = Article::TYPE[$request->get('type')];

        /** @var AbstractArticle $articleExtensions */
        $articleExtensions = new $type();
        $articleExtensions->article_uuid = $article->uuid;
        $articleExtensions->save();

        return $this->respond(Response::HTTP_CREATED, ResponseStrings::ARTICLE_STARTED, $article);
    }

    /**
     * Edit the specified article
     * @param Request $request The request parameters as passed by laravel
     * @return JsonResponse The newly created campaign or an error in json format
     */
    public function editArticle(Request $request, string $campaign_uuid, string $article_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|min:1|max:128',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $campaign = $this->getCampaign($campaign_uuid);
        $player = $this->getPlayer($campaign);
        $article = $this->getArticle($campaign, $player, $article_uuid);

        if ($request->has('name')) {
            $article->name = $request->get('name');
        }

        if ($request->has('description')) {
            $article->description = $request->get('description');
        }

        $article->save();

        return $this->respond(Response::HTTP_OK, GenericStringEnum::CHANGES_SAVED, true);
    }


}
