<?php

namespace Tests\Browser\tests\Config;

use App\Enum\Auth\UserEnum;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class UserTest extends DuskTestCase
{
    public function testOverviewDatatable(): void
    {
        $this->browse(function (Browser $browser) {
            $user = User::where('name', 'Tester')->first();
            $browser->loginAs($user)
                ->visit(route('config.user.overview'))
                ->with('.datatable', function (Browser $browser) use ($user) {
                    $browser->pause(250);
                    $browser
                        ->assertSourceHas(asset('img/profile/' . $user->profile_picture))
                        ->assertSee('Tester')
                        ->assertSee('test@test.nl')
                        ->assertSee('Tester');
                });
        });
    }

    public function testRoleChange(): void
    {
        $this->browse(function (Browser $browser) {
            $newRole = Role::where('name', 'Admin')->first();
            $user = User::where('name', 'Tester')->first();
            $browser->loginAs($user)
                ->visit(route('config.user.overview'))
                ->with('.datatable', function (Browser $browser) use ($user) {
                    $browser->pause(250);
                    $browser->click('@change_role_' . $user->uuid);
                });

            $browser->with('#role_modal', function (Browser $browser)  use ($user, $newRole) {
                $browser->pause(250);
                $browser
                    ->assertSelected('select', (string) $user->role_id)
                    ->select('select', $newRole->id)
                    ->click("@confirm");
            });

            $browser->pause(250);
            $browser
                ->assertRouteIs('config.user.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(UserEnum::USER_ROLE_CHANGED_MESSAGE);
                });
        });
    }

    public function testDeactiveUser(): void
    {
        $this->browse(function (Browser $browser) {
            $user = User::where('email', 'admin@test.nl')->first();
            $browser->loginAs($user)
                ->visit(route('config.user.overview'))
                ->with('.datatable', function (Browser $browser) use ($user) {
                    $browser->pause(250);
                    $browser->click('@delete_'.User::where('email', '!=', 'admin@test.nl')->first()->uuid);
                });

            $browser->with('#delete_modal', function (Browser $browser) {
                $browser->pause(250);
                $browser->click("@delete_confirm");
            });

            $browser->pause(250);
            $browser
                ->assertRouteIs('config.user.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(UserEnum::USER_DEACTIVATED_MESSAGE);
                });
        });
    }
}
