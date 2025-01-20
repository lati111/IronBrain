<?php

namespace App\Http\Api\Modules\Compendium;

use App\Enum\Modules\Compendium\ResponseStrings;
use App\Http\Api\AbstractApi;
use App\Models\Compendium\Campaign;
use App\Models\Compendium\Docs\Article;
use App\Models\Compendium\Player;
use Symfony\Component\HttpFoundation\Response;

class AbstractCompendiumDocsApi extends AbstractApi
{
    /**
     * Get a campaign by it's uuid.
     * Returns a redirect to campaign overview if campaign was not found.
     * @param string $campaign_uuid The uuid of the campaign.
     * @return Campaign The specified campaign.
     */
    protected function getCampaign(string $campaign_uuid): Campaign {
        $campain = Campaign::find($campaign_uuid);
        if ($campain === null) {
            abort($this->respond(Response::HTTP_NOT_FOUND, ResponseStrings::CAMPAIGN_NOT_FOUND, $campaign_uuid));
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
            abort($this->respond(Response::HTTP_NOT_FOUND, ResponseStrings::ARTICLE_NOT_FOUND, $article_uuid));
        }

        if ($article->playerHasAccess($player) === false) {
            abort($this->respond(Response::HTTP_FORBIDDEN, ResponseStrings::ARTICLE_NO_ACCESS, false));
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
            abort($this->respond(Response::HTTP_FORBIDDEN, ResponseStrings::CAMPAIGN_NO_ACCESS, false));
        }

        return $player;
    }
}
