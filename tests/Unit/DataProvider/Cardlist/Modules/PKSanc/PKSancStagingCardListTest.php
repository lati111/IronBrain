<?php

namespace DataProvider\Cardlist\Modules\PKSanc;

use App\Models\Auth\User;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Type;
use Carbon\Carbon;
use Database\Factories\Modules\PKSanc\StoredPokemonCreator;
use Illuminate\Http\JsonResponse;
use Tests\Unit\DataProvider\Datatable\AbstractDatatableTester;

class PKSancStagingCardListTest extends AbstractDatatableTester
{
    //| test data getting
    /** test grabbing the data from the cardlist */
    public function testData(): void
    {
        $user = $this->getAdminUser();
        $csv = ImportCsv::factory()->make([
            'uploader_uuid' => $user->uuid,
        ]);

        $factory = new StoredPokemonCreator();;
        $factory->pokemonvars = ['validated_at' => null];
        $factory->setUser($user);
        $factory->setCsv($csv);
        for ($i = 0; $i < 8; $i++) {
            $factory->create();
        }

        $route = route('pksanc.staging.cardlist', ['import_uuid' => $csv->uuid]);
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
        $csv = ImportCsv::factory()->make([
            'uploader_uuid' => $user->uuid,
        ]);

        $factory = new StoredPokemonCreator();
        $factory->pokemonvars = ['validated_at' => null];
        $factory->setUser($user);
        $factory->setCsv($csv);
        for ($i = 0; $i < 6; $i++) {
            $factory->create();
        }

        $factory->setUser($otherUser);
        for ($i = 0; $i < 2; $i++) {
            $factory->create();
        }

        $route = route('pksanc.staging.cardlist', ['import_uuid' => $csv->uuid]);
        $jsonResponse = $this->actingAs($user)->get($route, array_merge($this->getDefaultFilters(), []));
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertCount(6, $jsonResponse->json());
    }

    /** test that only the selected csv's pokemon are grabbed */
    public function testDataDifferentCsv(): void
    {
        $user = $this->getAdminUser();
        $csv = ImportCsv::factory()->make([
            'uploader_uuid' => $user->uuid,
        ]);
        $otherCsv = ImportCsv::factory()->make([
            'uploader_uuid' => $user->uuid,
        ]);

        $factory = new StoredPokemonCreator();
        $factory->pokemonvars = ['validated_at' => null];
        $factory->setUser($user);
        $factory->setCsv($csv);

        for ($i = 0; $i < 6; $i++) {
            $factory->create();
        }

        $factory->setCsv($otherCsv);
        for ($i = 0; $i < 2; $i++) {
            $factory->create();
        }

        $route = route('pksanc.staging.cardlist', ['import_uuid' => $csv->uuid]);
        $jsonResponse = $this->actingAs($user)->get($route, array_merge());
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertCount(6, $jsonResponse->json());
    }

    /** test that only unverified pokemons are grabbed */
    public function testDataVerified(): void
    {
        $user = $this->getAdminUser();
        $csv = ImportCsv::factory()->make([
            'uploader_uuid' => $user->uuid,
        ]);

        $factory = new StoredPokemonCreator();
        $factory->pokemonvars = ['validated_at' => Carbon::now()];
        $factory->setUser($user);
        $factory->setCsv($csv);

        for ($i = 0; $i < 6; $i++) {
            $factory->create();
        }

        $factory->pokemonvars = ['validated_at' => null];
        for ($i = 0; $i < 2; $i++) {
            $factory->create();
        }

        $route = route('pksanc.staging.cardlist', ['import_uuid' => $csv->uuid]);
        $jsonResponse = $this->actingAs($user)->get($route, array_merge());
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertCount(2, $jsonResponse->json());
    }

    /** test that the search function works */
    public function testDataSearch(): void
    {
        $user = $this->getAdminUser();
        $name = fake()->regexify('[A-Za-z-]{14}');
        $otherName = fake()->regexify('[A-Za-z]{8}');
        $csv = ImportCsv::factory()->make([
            'uploader_uuid' => $user->uuid,
        ]);

        $factory = new StoredPokemonCreator();
        $factory->setUser($user);
        $factory->setCsv($csv);
        $factory->pokemonvars = ['validated_at' => null, 'nickname' => $name];
        for ($i = 0; $i < 6; $i++) {
            $factory->create();
        }

        $factory->pokemonvars = ['validated_at' => null, 'nickname' => $otherName];
        for ($i = 0; $i < 2; $i++) {
            $factory->create();
        }

        $route = route('pksanc.staging.cardlist', ['import_uuid' => $csv->uuid, 'search' => $name]);
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
        $csv = ImportCsv::factory()->make([
            'uploader_uuid' => $user->uuid,
        ]);

        $factory = new StoredPokemonCreator();
        $factory->setUser($user);
        $factory->setCsv($csv);
        $factory->pokemonvars = ['validated_at' => null, 'tera_type' => $firstType];
        for ($i = 0; $i < 5; $i++) {
            $factory->create();
        }

        $factory->pokemonvars = ['validated_at' => null, 'tera_type' => $secondType];
        for ($i = 0; $i < 3; $i++) {
            $factory->create();
        }

        $route = route('pksanc.staging.cardlist', ['import_uuid' => $csv->uuid, 'filters' => json_encode([
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
        $csv = ImportCsv::factory()->make([
            'uploader_uuid' => $user->uuid,
        ]);

        $factory = new StoredPokemonCreator();;
        $factory->pokemonvars = ['validated_at' => null];
        $factory->setUser($user);
        $factory->setCsv($csv);
        for ($i = 0; $i < 14; $i++) {
            $factory->create();
        }

        $route = route('pksanc.staging.cardlist', ['import_uuid' => $csv->uuid, 'page' => 2, 'perpage' => 10]);
        $jsonResponse = $this->actingAs($user)->get($route);
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertCount(4, $jsonResponse->json());
    }

    //| test data counting
    /** test that the pagination function works */
    public function testCount(): void
    {
        $user = $this->getAdminUser();
        $csv = ImportCsv::factory()->make([
            'uploader_uuid' => $user->uuid,
        ]);

        $factory = new StoredPokemonCreator();;
        $factory->pokemonvars = ['validated_at' => null];
        $factory->setUser($user);
        $factory->setCsv($csv);
        for ($i = 0; $i < 15; $i++) {
            $factory->create();
        }

        $route = route('pksanc.staging.count', ['import_uuid' => $csv->uuid, 'perpage' => 10]);
        $jsonResponse = $this->actingAs($user)->get($route);
        $this->assertTrue($jsonResponse->baseResponse instanceof JsonResponse);
        $this->assertEquals(2, $jsonResponse->json());
    }
}
