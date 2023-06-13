<?php

namespace Tests\Browser\tests;

use App\Models\Auth\User;
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

    public function testProjectOverview(): void
    {

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/')
                ->with('@projects', function (Browser $browser) {
                    $browser->assertSee('Default Test Project');
                    $browser->assertSee('Visible Test Project');
                    $browser->assertDontSee('Invisible Test Project');
                });
        });
    }

    public function testNav(): void
    {

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/')
                ->with('@nav', function (Browser $browser) {
                    $browser->assertSee('Overview');
                    $browser->assertSee('Config');
                    $browser->assertDontSee('Admin');
                });
        });
    }
}
