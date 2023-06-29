<?php

namespace App\Service\PKSanc;

use App\Enum\PKSanc\ImportQueries;

class PokeApiService {

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
            throw new \ErrorException($error['message'], $error['type']);
        }

        return json_decode($data, true)["data"];
    }

    public function getTypes(): array
    {
        return $this->query(ImportQueries::TYPE_QUERY)['type'];
    }

    public function getNatures(): array
    {
        return $this->query(ImportQueries::NATURE_QUERY)['nature'];
    }

    public function getMoves(): array {
        return $this->query(ImportQueries::MOVE_QUERY)['move'];
    }

    public function getAbilities(): array {
        return $this->query(ImportQueries::ABILITY_QUERY)['ability'];
    }

    public function getPokeballs(): array {
        return $this->query(ImportQueries::POKEBALL_QUERY)['pokeball'];
    }

    public function getPokemon(): array {
        return $this->query(ImportQueries::POKEMON_QUERY)['pokemon'];
    }
}
