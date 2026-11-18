<?php

namespace Tests\Unit\Controller\Modules\PKSanc;

use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Service\PKSanc\DepositService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Tests\Traits\PKSanc\PKSancTestHelper;
use Tests\Unit\Controller\AbstractControllerUnitTester;
use Mockery;
use Mockery\MockInterface;

class PKSancDepositControllerTest extends AbstractControllerUnitTester
{
    use PKSancTestHelper;

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
        $this->assertView($response, 'modules.pksanc.deposit', ['gamesCollection']);
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
        $game = $this->createTestGame();

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
        $game = $this->createTestGame();

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
        $game = $this->createTestGame();

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


    //| show staged deposit attempt test
    /**
     * Test if controller returns the stage deposit view for a valid import uuid
     * @return void
     */
    public function testShowDepositAttempt(): void
    {
        $user = $this->getAdminUser();
        $game = $this->createTestGame();
        $csv = $this->createTestImportCsv($user->uuid, $game->game);

        $response = $this
            ->actingAs($user)
            ->get(route('pksanc.deposit.stage.show', $csv->uuid));
        $this->assertView($response, 'modules.pksanc.stage-deposit', ['importUuid']);
    }

    /**
     * Test that a non-existent import uuid returns a 404
     * @return void
     */
    public function testShowDepositAttemptNotFound(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('pksanc.deposit.stage.show', Str::uuid()));
        $response->assertNotFound();
    }


    //| cancel deposit test
    /**
     * Test if cancelling a deposit redirects to pksanc home
     * @return void
     */
    public function testDepositCancel(): void
    {
        $user = $this->getAdminUser();
        $game = $this->createTestGame();
        $csv = $this->createTestImportCsv($user->uuid, $game->game);

        $response = $this
            ->actingAs($user)
            ->get(route('pksanc.deposit.stage.cancel', $csv->uuid));
        $this->assertRedirect($response, 'pksanc.home.show');
    }

    /**
     * Test that cancelling with a non-existent import uuid returns a 404
     * @return void
     */
    public function testDepositCancelNotFound(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('pksanc.deposit.stage.cancel', Str::uuid()));
        $response->assertNotFound();
    }
}
