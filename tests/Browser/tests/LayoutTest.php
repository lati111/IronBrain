<?php

namespace Tests\Browser\tests;

use App\Models\Auth\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LayoutTest extends DuskTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ProjectSeeder']);
        $this->artisan('db:seed', ['--class' => 'NavSeeder']);
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

    public function testNavSubmenu(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/')
                ->with('@nav', function (Browser $browser) {
                    $browser->click('@Config')
                        ->with('@Config', function (Browser $browser) {
                            $browser->assertSee('Default');
                            $browser->assertSee('Visible');
                            $browser->assertDontSee('Invisible');
                        });
                });
        });
    }

    public function testLoggedIn(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/')
                ->click('@pfp_dropdown_toggle')
                ->with('@pfp_dropdown', function (Browser $browser) {
                    $browser->assertSee('Sign out');
                });
        });
    }

    public function testAuthNotLoggedIn(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->with('@auth_header', function (Browser $browser) {
                    $browser->assertSee('Log In');
                    $browser->assertSee('Sign Up');
                });
        });
    }
}
