<?php

namespace App\Service\PKSanc;

use App\Enum\PKSanc\ImportQueryEnum;

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

        return json_decode($data, true);
    }

    public function getTypes(): array
    {
        return $this->query(ImportQueryEnum::TYPE_QUERY);
    }

    public function getNatures(): array
    {
        return $this->query(ImportQueryEnum::NATURE_QUERY);
    }

    public function getMoves(): array {
        return $this->query(ImportQueryEnum::MOVE_QUERY);
    }

    public function getAbilities(): array {
        return $this->query(ImportQueryEnum::ABILITY_QUERY);
    }

    public function getPokeballs(): array {
        return $this->query(ImportQueryEnum::POKEBALL_QUERY);
    }

    public function getPokemon(): array {
        return $this->query(ImportQueryEnum::POKEMON_QUERY);
    }
}
