<?php

namespace Tests\Browser\tests;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class HomeTest extends DuskTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ProjectSeeder']);
        $this->artisan('db:seed', ['--class' => 'NavSeeder']);
    }

    public function testOverviewDefault(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertSee('IronBrain Webtools');
        });
    }
}
