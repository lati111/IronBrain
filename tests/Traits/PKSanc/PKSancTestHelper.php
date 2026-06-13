<?php

namespace Tests\Traits\PKSanc;

use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use Illuminate\Support\Str;

trait PKSancTestHelper
{
    protected function createTestGame(?string $gameCode = null): Game
    {
        $code = $gameCode ?? 'test_' . Str::random(6);

        $existing = Game::where('game', $code)->first();
        if ($existing !== null) {
            return $existing;
        }

        $game = new Game();
        $game->game = $code;
        $game->name = 'Test Game';
        $game->is_romhack = false;
        $game->save();
        return $game;
    }

    protected function createTestImportCsv(string $ownerUuid, string $gameCode): ImportCsv
    {
        $csv = new ImportCsv();
        $csv->csv = 'test.csv';
        $csv->game = $gameCode;
        $csv->name = 'Test Import';
        $csv->version = 1.2;
        $csv->validated = 0;
        $csv->uploader_uuid = $ownerUuid;
        $csv->save();
        return $csv;
    }
}
