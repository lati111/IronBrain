<?php

namespace App\Service\Arsenal;

use App\Enum\PKSanc\ImportQueries;
use ErrorException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class WarframeApiService {
    protected Client $client;
    protected const array headers = ['Content-Type: application/json', 'User-Agent: IronBrain'];

    public function __construct() {
        $this->client = new Client([
            'connect_timeout' => 10.0,
            'timeout'         => 120.0,
        ]);
    }

    //| Import query

    /**
     * Gets a list of warframes
     * @return array Returns an array of types
     * @throws ErrorException|GuzzleException
     */
    public function getWarframes(): array
    {
        return $this->queryItems('warframes', [
            'name',
            'uniqueName',
            'description',
            'imageName',
            'wikiaUrl',
            'isPrime',
            'components',
            'productCategory',
            'exaltedWeapons',
        ]);
    }

    /**
     * Gets a list of weapons
     * @return array Returns an array of types
     * @throws ErrorException|GuzzleException
     */
    public function getWeapons(): array
    {
        return $this->queryItems('primary,secondary,melee,arch-gun,arch-melee,companion-weapon', [
            'name',
            'uniqueName',
            'description',
            'imageName',
            'wikiaUrl',
            'isPrime',
            'components',
            'type',
            'category',
            'productCategory'
        ]);
    }

    /**
     * Gets a list of weapons
     * @return array Returns an array of types
     * @throws ErrorException|GuzzleException
     */
    public function getCompanions(): array
    {
        return $this->queryItems('pets,sentinels', [
            'name',
            'uniqueName',
            'description',
            'imageName',
            'wikiaUrl',
            'isPrime',
            'components',
            'category',
            'productCategory'
        ]);
    }

    //| Queries

    /**
     * Get all items in the category list
     * @param string|null $category The category to filter by
     * @param array $whitelist The whitelist or fields to return
     * @return array The result of the API
     * @throws ErrorException
     * @throws GuzzleException
     */
    private function queryItems(string|null $category = null, array $whitelist = []): array {
        $url = $category !== null ? sprintf('https://api.warframestat.us/items/search/%s?by=category', $category) : sprintf('https://api.warframestat.us/items?by=category');
        return $this->queryApi($url, $whitelist);
    }

    /**
     * Make a query to the API
     * @param array $whitelist The whitelist or fields to return
     * @return array The result of the API
     * @throws ErrorException
     * @throws GuzzleException
     */
    private function queryApi(string $url, array $whitelist = []): array {
        if ($whitelist !== []) {
            $url .= sprintf('%sonly=%s',
                str_contains($url, '?') ? '&' : '?',
                implode(',', $whitelist)
            );
        }

        $response = $this->client->get($url, [
            'headers' => self::headers,
        ]);

        $data = json_decode($response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ErrorException('Invalid JSON response: ' . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new ErrorException('Unexpected API response: expected array, got ' . gettype($data));
        }

        return $data;
    }

}
