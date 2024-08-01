<?php

namespace Tests\Browser\tests;

use App\Enum\Auth\UserEnum;
use App\Models\Auth\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LoginTest extends DuskTestCase
{
    public function testSignUp(): void
    {

        $this->browse(function (Browser $browser) {
            $browser
                ->visit('/')
                ->click('@signup')
                ->assertRouteIs('auth.signup.show')
                ->with('@form', function (Browser $browser) {
                    $browser
                        ->type('@name_input', 'user44')
                        ->type('@email_input', 'user44@ironbrain.io')
                        ->type('@password_input', 'Password123')
                        ->type('@repeat_password_input', 'Password123')
                        ->press('@submitter');
                });

            $browser->pause(250);
            $browser
                ->assertRouteIs('auth.login.show')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(UserEnum::SIGNUP_SUCCESS_MESSAGE);
                });
        });
    }

    public function testLogin(): void
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visit('/')
                ->click('@login')
                ->assertRouteIs('auth.login.show')
                ->with('@form', function (Browser $browser) {
                    $browser
                        ->type('@email_input', 'test@test.nl')
                        ->type('@password_input', 'Password123')
                        ->check('#remember_me')
                        ->press('@submitter');
                });

            $browser->pause(250);
            $browser
                ->assertRouteIs('home.show')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(UserEnum::LOGGED_IN);
                });
        });
    }

    public function testLogout(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/')
                ->click('@pfp_dropdown_toggle')
                ->with('@pfp_dropdown', function (Browser $browser) {
                    $browser->click('@logout');
                });

            $browser->pause(250);
            $browser
                ->assertRouteIs('auth.login.show')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(UserEnum::LOGOUT_MESSAGE);
                });
        });
    }
}
