<?php

namespace Tests\Feature\Api\PKSanc\ContributionApi;

use App\Enum\PKSanc\ContributionStrings;
use App\Models\PKSanc\Game;
use Illuminate\Support\Facades\Lang;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\PKSanc\PKSancTestHelper;

class AddRomhackTest extends AbstractApiFeatureTester
{
    use PKSancTestHelper;

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return route('api.pksanc.games.romhacks.add');
    }

    protected function getModel(): Game
    {
        return new Game();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name' => fake()->words(2, true),
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $user = $this->getOperationUser();

        // required
        $response = $this->getHttpClient($user)->post($this->getRoute(), []);
        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.required', ['attribute' => 'name']),
            $response->json()['name']
        );

        // string
        $response = $this->getHttpClient($user)->post($this->getRoute(), ['name' => 42]);
        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.string', ['attribute' => 'name']),
            $response->json()['name']
        );

        // max:255
        $response = $this->getHttpClient($user)->post($this->getRoute(), ['name' => str_repeat('a', 256)]);
        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.max.string', ['attribute' => 'name', 'max' => 255]),
            $response->json()['name']
        );
    }

    public function test_original_game_validation(): void
    {
        $user = $this->getOperationUser();

        // nullable — no error when absent
        $response = $this->getHttpClient($user)->post($this->getRoute(), ['name' => 'Test Hack']);
        $this->assertNull($response->json()['original_game'] ?? null);

        // exists — error when game code doesn't exist
        $response = $this->getHttpClient($user)->post($this->getRoute(), [
            'name'          => 'Test Hack',
            'original_game' => 'nonexistent_game_code',
        ]);
        $response->assertBadRequest();
        $this->assertContains(
            Lang::get('validation.exists', ['attribute' => 'original game']),
            $response->json()['original_game']
        );
    }

    //| add romhack tests

    public function test_add_romhack(): void
    {
        $name = 'My Romhack';
        $expectedCode = 'my_romhack';

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['name' => $name]);

        $response->assertCreated();
        $this->assertApiMessage(ContributionStrings::ROMHACK_ADDED, $response);

        $game = Game::where('game', $expectedCode)->first();
        $this->assertNotNull($game);
        $this->assertEquals($name, $game->name);
        $this->assertTrue($game->is_romhack);
    }

    public function test_add_romhack_code_collision(): void
    {
        $name = 'My Romhack';
        $baseCode = 'my_romhack';

        // create a game with the base code so there's a collision
        $this->createTestGame($baseCode);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['name' => $name]);

        $response->assertCreated();

        $game = Game::where('game', $baseCode . '_1')->first();
        $this->assertNotNull($game);
        $this->assertEquals($name, $game->name);
    }

    public function test_add_romhack_multiple_collisions(): void
    {
        $name = 'My Romhack';
        $baseCode = 'my_romhack';

        // occupy both the base code and _1
        $this->createTestGame($baseCode);
        $this->createTestGame($baseCode . '_1');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['name' => $name]);

        $response->assertCreated();

        $game = Game::where('game', $baseCode . '_2')->first();
        $this->assertNotNull($game);
        $this->assertEquals($name, $game->name);
    }

    public function test_add_romhack_with_original_game(): void
    {
        $original = $this->createTestGame();
        $name = 'My Romhack';

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'name'          => $name,
                'original_game' => $original->game,
            ]);

        $response->assertCreated();

        $game = Game::where('game', 'my_romhack')->first();
        $this->assertNotNull($game);
        $this->assertEquals($original->game, $game->original_game);
    }
}
