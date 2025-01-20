<?php

namespace App\Http\Controllers\Modules\Compendium;

use App\Enum\Modules\Compendium\ResponseStrings;
use App\Http\Controllers\Controller;
use App\Models\Compendium\Campaign;
use App\Models\Compendium\Docs\Article;
use App\Models\Compendium\Player;
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
        $campaign = $this->getCampaign($campaign_uuid);
        $player = $this->getPlayer($campaign);

        return $this->view('modules.compendium.campaign', [
            'campaign' => $campaign,
            'player' => $player
        ]);
    }

    /**
     * Shows article page
     * @return View|RedirectResponse Returns a View of the page, or a redirect to the overview
     */
    public function article(string $campaign_uuid, string $article_uuid): View|RedirectResponse
    {
        $campaign = $this->getCampaign($campaign_uuid);
        $player = $this->getPlayer($campaign);

        $article = $this->getArticle($campaign, $player, $article_uuid);
        $article->with($article->type);

        return $this->view('modules.compendium.article', [
            'article' => $article,
            'campaign' => $campaign,
            'player' => $player
        ]);
    }

    /**
     * Get a campaign by it's uuid.
     * Returns a redirect to campaign overview if campaign was not found.
     * @param string $campaign_uuid The uuid of the campaign.
     * @return Campaign The specified campaign.
     */
    protected function getCampaign(string $campaign_uuid): Campaign {
        $campain = Campaign::find($campaign_uuid);
        if ($campain === null) {
            abort(redirect(route('compendium.campaigns'))->with(['error' => ResponseStrings::CAMPAIGN_NOT_FOUND]));
        }

        return $campain;
    }

    /**
     * Get a campaign by it's uuid.
     * Returns a redirect to campaign page if article was not found.
     * Returns a redirect to campaign page if player does not have access to the article.
     *
     * @param Campaign $campaign The campaign to take the article from
     * @param Player $player The player trying to view the article
     * @param string $article_uuid The uuid of the article.
     * @return Article The specified article.
     */
    protected function getArticle(Campaign $campaign, Player $player, string $article_uuid): Article {
        /** @var Article $article */
        $article = $campaign->articles()->where('uuid', $article_uuid)->first();
        if ($article === null) {
            abort(redirect(route('compendium.campaign', ['campaign_uuid' => $campaign->uuid]))->with(['error' => ResponseStrings::ARTICLE_NOT_FOUND]));
        }

        if ($article->playerHasAccess($player) === false) {
            abort(redirect(route('compendium.campaign', ['campaign_uuid' => $campaign->uuid]))->with(['error' => ResponseStrings::ARTICLE_NO_ACCESS]));
        }

        return $article;
    }

    /**
     * Get player from a campaign based on the current user.
     * Returns a redirect to campaign overview if player does not have access to campaign
     *
     * @param Campaign $campaign The campaign to take the player from
     * @return Player The player.
     */
    protected function getPlayer(Campaign $campaign): Player {
        $player = $campaign->findPlayerByUser(\Auth::user()->uuid);
        if ($player === null) {
            abort(redirect(route('compendium.campaigns'))->with(['error' => ResponseStrings::CAMPAIGN_NO_ACCESS]));
        }

        return $player;
    }
}
