<?php

namespace DataProvider\Cardlist\Modules\PKSanc;

use App\Models\Auth\Permission;
use App\Models\Auth\User;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Type;
use Database\Factories\Modules\PKSanc\StoredPokemonCreator;
use Database\Factories\Modules\PKSanc\StoredPokemonFactory;
use Illuminate\Http\JsonResponse;
use Tests\Unit\DataProvider\Datatable\AbstractDatatableTester;

class PKSancOverviewCardListTest extends AbstractDatatableTester
{
    //| test data getting
    /** test grabbing the data from the cardlist */
    public function testData(): void
    {
        $user = $this->getAdminUser();

        $factory = new StoredPokemonCreator();
        $factory->setUser($user);
        for ($i = 0; $i < 8; $i++) {
            $factory->create();
        }

        $route = route('pksanc.overview.cardlist');
        $jsonResponse = $this->actingAs($user)->get($route, array_merge($this->getDefaultFilters(), []));
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertCount(8, $jsonResponse->json());
    }

    /** test that only the user's pokemon are grabbed */
    public function testDataDifferentUser(): void
    {
        $user = $this->getAdminUser();
        $otherUser = User::factory()->make();
        $otherUser->save();

        $factory = new StoredPokemonCreator();
        $factory->setUser($user);
        for ($i = 0; $i < 6; $i++) {
            $factory->create();
        }

        $factory->setUser($otherUser);
        for ($i = 0; $i < 2; $i++) {
            $factory->create();
        }

        $route = route('pksanc.overview.cardlist');
        $jsonResponse = $this->actingAs($user)->get($route, array_merge($this->getDefaultFilters(), []));
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertCount(6, $jsonResponse->json());
    }

    /** test that the search function works */
    public function testDataSearch(): void
    {
        $user = $this->getAdminUser();
        $name = fake()->regexify('[A-Za-z-]{14}');
        $otherName = fake()->regexify('[A-Za-z]{8}');

        $factory = new StoredPokemonCreator();
        $factory->setUser($user);
        $factory->pokemonvars = ['nickname' => $name];
        for ($i = 0; $i < 6; $i++) {
            $factory->create();
        }

        $factory->pokemonvars = ['nickname' => $otherName];
        for ($i = 0; $i < 2; $i++) {
            $factory->create();
        }

        $route = route('pksanc.overview.cardlist', ['search' => $name]);
        $jsonResponse = $this->actingAs($user)->get($route, array_merge($this->getDefaultFilters(), []));
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertCount(6, $jsonResponse->json());
    }

    /** test that the filter function works */
    public function testDataFiltering(): void
    {
        $user = $this->getAdminUser();
        $firstType = Type::inRandomOrder()->first();
        $secondType = Type::inRandomOrder()->where('type', '!=', $firstType->type)->first();

        $factory = new StoredPokemonCreator();
        $factory->setUser($user);
        $factory->pokemonvars = ['tera_type' => $firstType];
        for ($i = 0; $i < 5; $i++) {
            $factory->create();
        }

        $factory->pokemonvars = ['tera_type' => $secondType];
        for ($i = 0; $i < 3; $i++) {
            $factory->create();
        }

        $route = route('pksanc.overview.cardlist', ['filters' => json_encode([
            ['filter' => 'tera_type', 'operator' => '=', 'value' => $firstType->type]
        ])]);

        $jsonResponse = $this->actingAs($user)->get($route, array_merge($this->getDefaultFilters(), []));
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertCount(5, $jsonResponse->json());
    }

    /** test that the pagination function works */
    public function testDataPagination(): void
    {
        $user = $this->getAdminUser();

        $factory = new StoredPokemonCreator();
        $factory->setUser($user);
        for ($i = 0; $i < 14; $i++) {
            $factory->create();
        }

        $route = route('pksanc.overview.cardlist', ['page' => 2, 'perpage' => 10]);
        $jsonResponse = $this->actingAs($user)->get($route);
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertCount(4, $jsonResponse->json());
    }

    //| test data counting
    /** test that the pagination function works */
    public function testCount(): void
    {
        $user = $this->getAdminUser();

        $factory = new StoredPokemonCreator();
        $factory->setUser($user);
        for ($i = 0; $i < 15; $i++) {
            $factory->create();
        }

        $route = route('pksanc.overview.count', ['perpage' => 10]);
        $jsonResponse = $this->actingAs($user)->get($route);
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertEquals(2, $jsonResponse->json());
    }
}
