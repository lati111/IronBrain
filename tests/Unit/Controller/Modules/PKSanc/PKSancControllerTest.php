<?php

namespace Tests\Unit\Controller\Modules\PKSanc;

use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Service\PKSanc\DepositService;
use Database\Seeders\Module\PKSancSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use Tests\Unit\Controller\AbstractControllerUnitTester;
use Mockery;
use Mockery\MockInterface;

class PKSancControllerTest extends AbstractControllerUnitTester
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


    //| show overview test
    /**
     * Test if controller returns proper view
     * @return void
     */
    public function testOverviewShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('pksanc.home.show'));
        $this->assertView($response, 'modules.pksanc.home');
    }
}
