<?php

namespace Service\Modules\PKSanc;

use App\Exceptions\Modules\PKSanc\ImportException;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\StoredPokemon;
use App\Service\PKSanc\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\Unit\Service\AbstractServiceTester as ServiceAbstractServiceTester;

class DepositServiceTest extends ServiceAbstractServiceTester
{
    /**
     * Tests the importing of a v1 csv
     * @return void
     * @throws \Throwable
     */
    public function testStageImportV1(): void
    {
        $user = $this->getAdminUser();
        Auth::login($user);

        $csv = New ImportCsv();
        $csv->csv = __DIR__ . '/../../../../Data/Modules/PKSanc/main_v1.2.csv';
        $csv->game = 'X';
        $csv->name = 'test save';
        $csv->version = 1.2;
        $csv->validated = 0;
        $csv->uploader_uuid = $user->uuid;
        $csv->save();

        $mock = Mockery::mock('App\Models\PKSanc\ImportCsv');
        $mock->shouldReceive('getAttribute')
            ->with('uuid')
            ->andReturn($csv->uuid);
        $mock->shouldReceive('getAttribute')
            ->with('game')
            ->andReturn($csv->game);
        $mock->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn($csv->name);
        $mock->shouldReceive('getAttribute')
            ->with('version')
            ->andReturn($csv->version);
        $mock->shouldReceive('getAttribute')
            ->with('validated')
            ->andReturn($csv->validated);
        $mock->shouldReceive('getAttribute')
            ->with('uploader_uuid')
            ->andReturn($csv->uploader_uuid);
        $mock->shouldReceive('getCsvPath')->once()
            ->andReturn(__DIR__ . '/../../../../Data/Modules/PKSanc/main_v1.2.csv');

        $depositService = new DepositService();
        $depositService->stageImport($mock);

        $pokemons = $csv->Pokemon()->get();
        $this->assertCount(2, $pokemons);

        /** @var StoredPokemon $pokemon */
        $pokemon = $pokemons->first();

        //validate staging
        $staging = $pokemon->getStaging();
        $this->assertNotNull($staging);

        //validate pokemon
        $this->assertEquals('2241049555', $pokemon->PID);
        $this->assertEquals('Mimoria', $pokemon->nickname);
        $this->assertEquals('bramblin', $pokemon->pokemon);
        $this->assertEquals('F', $pokemon->gender);
        $this->assertEquals('modest', $pokemon->nature);
        $this->assertEquals('windrider', $pokemon->ability);
        $this->assertEquals('heavy-ball', $pokemon->pokeball);
        $this->assertEquals('flying', $pokemon->hidden_power_type);
        $this->assertEquals('grass', $pokemon->tera_type);
        $this->assertEquals(50, $pokemon->friendship);
        $this->assertEquals(5, $pokemon->level);
        $this->assertEquals(60, $pokemon->height);
        $this->assertEquals(6, $pokemon->weight);
        $this->assertTrue($pokemon->is_shiny);
        $this->assertFalse($pokemon->is_alpha);
        $this->assertFalse($pokemon->has_n_sparkle);
        $this->assertFalse($pokemon->can_gigantamax);
        $this->assertEquals(0, $pokemon->dynamax_level);
        $this->assertNull($pokemon->validated_at);

        //validate origin
        $origin = $pokemon->getOrigin();
        $this->assertNotNull($origin);
        $this->assertEquals('vl', $origin->game);
        $this->assertEquals('2023-04-18', $origin->met_date);
        $this->assertEquals('Medali', $origin->met_location);
        $this->assertEquals(0, $origin->met_level);
        $this->assertTrue($origin->was_egg);

        //validate trainer
        $trainer = $origin->getTrainer();
        $this->assertNotNull($trainer);
        $this->assertEquals('48526', $trainer->trainer_id);
        $this->assertEquals('35783', $trainer->secret_id);
        $this->assertEquals('Sophie', $trainer->name);
        $this->assertEquals('F', $trainer->gender);
        $this->assertEquals('vl', $trainer->game);

        //validate stats
        $stats = $pokemon->getStats();
        $this->assertNotNull($stats);
        $this->assertEquals(23, $stats->hp_iv);
        $this->assertEquals(105, $stats->hp_ev);
        $this->assertEquals(6, $stats->atk_iv);
        $this->assertEquals(180, $stats->atk_ev);
        $this->assertEquals(23, $stats->def_iv);
        $this->assertEquals(53, $stats->def_ev);
        $this->assertEquals(12, $stats->spa_iv);
        $this->assertEquals(57, $stats->spa_ev);
        $this->assertEquals(12, $stats->spd_iv);
        $this->assertEquals(83, $stats->spd_ev);
        $this->assertEquals(8, $stats->spe_iv);
        $this->assertEquals(32, $stats->spe_ev);

        $contentStats = $pokemon->getContestStats();
        $this->assertNotNull($contentStats);
        $this->assertEquals(5, $contentStats->beauty);
        $this->assertEquals(10, $contentStats->cool);
        $this->assertEquals(5, $contentStats->cute);
        $this->assertEquals(175, $contentStats->smart);
        $this->assertEquals(25, $contentStats->tough);
        $this->assertEquals(55, $contentStats->sheen);

        $moveset = $pokemon->getMoveset();
        $this->assertNotNull($moveset);
        $this->assertEquals('shadowsneak', $moveset->move1);
        $this->assertEquals(0, $moveset->move1_pp_up);
        $this->assertEquals('trailblaze', $moveset->move2);
        $this->assertEquals(0, $moveset->move2_pp_up);
        $this->assertEquals('pounce', $moveset->move3);
        $this->assertEquals(0, $moveset->move3_pp_up);
        $this->assertNull($moveset->move4);
        $this->assertEquals(0, $moveset->move4_pp_up);
    }

    /**
     * Tests the importing of an unknown csv version
     * @return void
     * @throws \Throwable
     */
    public function testStageImportUnknownVersion(): void
    {
        $user = $this->getAdminUser();
        Auth::login($user);

        $csv = New ImportCsv();
        $csv->csv = __DIR__ . '/../../../../Data/Modules/PKSanc/main_unknown.csv';
        $csv->game = 'X';
        $csv->name = 'test save';
        $csv->version = 0;
        $csv->validated = 0;
        $csv->uploader_uuid = $user->uuid;
        $csv->save();

        $mock = Mockery::mock('App\Models\PKSanc\ImportCsv');
        $mock->shouldReceive('getAttribute')
            ->with('uuid')
            ->andReturn($csv->uuid);
        $mock->shouldReceive('getAttribute')
            ->with('game')
            ->andReturn($csv->game);
        $mock->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn($csv->name);
        $mock->shouldReceive('getAttribute')
            ->with('version')
            ->andReturn($csv->version);
        $mock->shouldReceive('getAttribute')
            ->with('validated')
            ->andReturn($csv->validated);
        $mock->shouldReceive('getAttribute')
            ->with('uploader_uuid')
            ->andReturn($csv->uploader_uuid);
        $mock->shouldReceive('getCsvPath')->once()
            ->andReturn(__DIR__ . '/../../../../Data/Modules/PKSanc/main_unknown.csv');

        $depositService = new DepositService();
        $this->expectException(ImportException::class);
        $depositService->stageImport($mock);
    }

    /**
     * tests marking an import as confirmed
     * @return void
     * @throws \Throwable
     */
    public function testStagingConfirm(): void
    {
        $user = $this->getAdminUser();
        Auth::login($user);

        $csv = New ImportCsv();
        $csv->csv = __DIR__ . '/../../../../Data/Modules/PKSanc/main_v1.2.csv';
        $csv->game = 'X';
        $csv->name = 'test save';
        $csv->version = 1.2;
        $csv->validated = 0;
        $csv->uploader_uuid = $user->uuid;
        $csv->save();

        $mock = Mockery::mock('App\Models\PKSanc\ImportCsv');
        $mock->shouldReceive('getAttribute')
            ->with('uuid')
            ->andReturn($csv->uuid);
        $mock->shouldReceive('getAttribute')
            ->with('game')
            ->andReturn($csv->game);
        $mock->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn($csv->name);
        $mock->shouldReceive('getAttribute')
            ->with('version')
            ->andReturn($csv->version);
        $mock->shouldReceive('getAttribute')
            ->with('validated')
            ->andReturn($csv->validated);
        $mock->shouldReceive('getAttribute')
            ->with('uploader_uuid')
            ->andReturn($csv->uploader_uuid);
        $mock->shouldReceive('getCsvPath')->once()
            ->andReturn(__DIR__ . '/../../../../Data/Modules/PKSanc/main_v1.2.csv');

        $depositService = new DepositService();
        $depositService->stageImport($mock);

        /** @var StoredPokemon $pokemon */
        foreach ($csv->Pokemon()->get() as $pokemon) {
            $depositService->confirmStaging($pokemon->getStaging());
        }
    }
}
