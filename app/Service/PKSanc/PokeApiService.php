<?php

namespace App\Service\PKSanc;

use App\Enum\PKSanc\ImportQueries;
use ErrorException;

class PokeApiService {

    /**
     * Executes a graphQL query in PokeAPI and returns the results
     * @param string $query The graphQL query to execute
     * @return array Returns the results from the graphQL query
     * @throws ErrorException
     */
    private function query(string $query): array {
        $url = 'https://beta.pokeapi.co/graphql/v1beta';
        $headers = ['Content-Type: application/json', 'User-Agent: IronBrain'];

        if (false === $data = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => json_encode(['query' => $query]),
            ]
        ]))) {
            $error = error_get_last();
            throw new ErrorException($error['message'], $error['type']);
        }

        return json_decode($data, true)["data"];
    }

    /**
     * Gets a list of types from PokeAPI
     * @return array Returns an array of types
     * @throws ErrorException
     */
    public function getTypes(): array
    {
        return $this->query(ImportQueries::TYPE_QUERY)['type'];
    }

    /**
     * Gets a list of natures from PokeAPI
     * @return array Returns an array of natures
     * @throws ErrorException
     */
    public function getNatures(): array
    {
        return $this->query(ImportQueries::NATURE_QUERY)['nature'];
    }

    /**
     * Gets a list of moves from PokeAPI
     * @return array Returns an array of moves
     * @throws ErrorException
     */
    public function getMoves(): array {
        return $this->query(ImportQueries::MOVE_QUERY)['move'];
    }

    /**
     * Gets a list of abilities from PokeAPI
     * @return array Returns an array of abilities
     * @throws ErrorException
     */
    public function getAbilities(): array {
        return $this->query(ImportQueries::ABILITY_QUERY)['ability'];
    }

    /**
     * Gets a list of pokeballs from PokeAPI
     * @return array Returns an array of pokeballs
     * @throws ErrorException
     */
    public function getPokeballs(): array {
        return $this->query(ImportQueries::POKEBALL_QUERY)['pokeball'];
    }

    /**
     * Gets a list of pokemon from PokeAPI
     * @return array Returns an array of pokemon
     * @throws ErrorException
     */
    public function getPokemon(): array {
        return $this->query(ImportQueries::POKEMON_QUERY)['pokemon'];
    }
}
