<?php

namespace Controller\Modules\PKSanc;

use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Service\PKSanc\DepositService;
use Database\Seeders\Module\PKSancSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use Tests\Unit\Controller\AbstractControllerUnitTester;
use Mockery;
use Mockery\MockInterface;

class PKSancDepositControllerTest extends AbstractControllerUnitTester
{
    /**
     * Seeds the database for the tests
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->seed(PKSancSeeder::class);
    }


    //| show deposit test
    /**
     * Test if controller returns proper view
     * @return void
     */
    public function testDepositShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('pksanc.deposit.show'));
        $this->assertView($response, 'project.pksanc.deposit', ['gamesCollection']);
    }


    //| show staged deposit
    /**
     * Test if controller returns proper view
     * @return void
     */
    public function testStagedDepositShow(): void
    {
        $route = route('pksanc.deposit.stage.attempt');

        /** @var Game $game */
        $game = $this->getRandomEntity(Game::class);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'game' =>  $game->game,
            ]);
        $this->assertValidationValid('game');

        //exists
        $this->post($route, [
            'game' => $this->getFalseIdentifierString(Game::class, 'game')
        ]);
        $this->assertValidationExists('game');

        //is required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('game');
    }


    //| stage deposit attempt
    /**
     * Test if validations works when all parameters are valid
     * @return void
     */
    public function testDepositAttemptValid(): void
    {
        $route = route('pksanc.deposit.stage.attempt');

        /** @var Game $game */
        $game = $this->getRandomEntity(Game::class);

        $user = $this->getAdminUser();
        $name = $this->faker->regexify('[A-Za-z0-9]{124}');

        $this->instance(
            DepositService::class,
            Mockery::mock(DepositService::class, function (MockInterface $mock) {
                $mock->shouldReceive('stageImport')->once();
            })
        );

        Storage::shouldReceive('putFileAs')->once();

        $response = $this
            ->actingAs($user)
            ->post($route, [
                'name' => $name,
                'csv' => UploadedFile::fake()->create('test.csv'),
                'game' => $game->game,
            ]);

        $this->assertValidationValid('name');
        $this->assertValidationValid('csv');
        $this->assertValidationValid('game');

        $importCsv = ImportCsv::where('name', $name)->where('game', $game->game)->first();
        $this->assertNotNull($importCsv);
        $this->assertEquals($name, $importCsv->name);
        $this->assertEquals($game->game, $importCsv->game);
        $this->assertEquals($user->uuid, $importCsv->uploader_uuid);

        $this->assertRedirectWithRouteParams($response, 'pksanc.deposit.stage.show', [$importCsv->uuid]);
    }

    /**
     * Test if name is validated correctly
     * @return void
     */
    public function testDepositAttemptNameValidation(): void
    {
        $route = route('pksanc.deposit.stage.attempt');

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' => $this->faker->regexify('[A-Za-z0-9]{64}'),
            ]);
        $this->assertValidationValid('name');

        //is required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('name');

        //too long
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' => $this->faker->regexify('[A-Za-z0-9]{266}'),
            ]);
        $this->assertValidationTooLong('name', 255);

        //is string
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' => 44,
            ]);
        $this->assertValidationString('name');
    }

    /**
     * Test if csv is validated correctly
     * @return void
     */
    public function testDepositAttemptCsvValidation(): void
    {
        $route = route('pksanc.deposit.stage.attempt');

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'csv' => UploadedFile::fake()->create('test.csv'),
            ]);
        $this->assertValidationValid('csv');

        //is required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('csv');

        //is csv filetype
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'csv' => UploadedFile::fake()->create('test.fake'),
            ]);
        $this->assertValidationCsvFileType('csv');
    }

    /**
     * Test if game is validated correctly
     * @return void
     */
    public function testDepositAttemptGameValidation(): void
    {
        $route = route('pksanc.deposit.stage.attempt');

        /** @var Game $game */
        $game = $this->getRandomEntity(Game::class);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'game' =>  $game->game,
            ]);
        $this->assertValidationValid('game');

        //exists
        $this->post($route, [
            'game' => $this->getFalseIdentifierString(Game::class, 'game')
        ]);
        $this->assertValidationExists('game');

        //is required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('game');
    }



}
