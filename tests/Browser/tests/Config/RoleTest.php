<?php

namespace Tests\Browser\tests\Config;

use App\Enum\Auth\RoleEnum;
use App\Models\Auth\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class RoleTest extends DuskTestCase
{
    public function testOverviewDatatable(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.role.overview'))
                ->with('.datatable tbody', function (Browser $browser) {
                    $browser->pause(250);
                    $browser
                        ->assertSee('Tester')
                        ->assertSee('Default test role');
                });
        });
    }

    public function testCreateRole(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.role.overview'))
                ->click('@new_role')
                ->assertRouteIs('config.role.new')

                ->with('@form', function (Browser $browser) {
                    $browser
                        ->type('@name_input', 'Dummy role')
                        ->type('@description_input', 'A fake role for testing')
                        ->click('@submitter');
                })
                ->assertRouteIs('config.role.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(RoleEnum::ROLE_SAVED_MESSAGE);
                });
        });
    }

    public function testUpdateRole(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.role.overview'));
            $browser->pause(250);
            $browser
                ->with('.datatable', function (Browser $browser) {
                    $browser->click('@modify_2');
                });
            $browser->assertRouteIs('config.role.modify', [2]);

            $browser
                ->with('@form', function (Browser $browser) {
                    $browser
                        ->assertValue('@name_input', 'Admin')
                        ->assertValue('@description_input', 'Admin test role')

                        ->type('@name_input', '123')
                        ->click('@submitter');
                })
                ->assertRouteIs('config.role.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(RoleEnum::ROLE_SAVED_MESSAGE);
                });
        });
    }

    public function testRoleModifyOldValues(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.role.overview'))
                ->click('@new_role')
                ->assertRouteIs('config.role.new')

                ->with('@form', function (Browser $browser) {
                    $browser
                        ->type('@name_input', 'qwertyuiopasdfghjklzxcvbnmqwertyuiop')
                        ->type('@description_input', 'A fake role for testing')
                        ->click('@submitter');
                })
                ->assertRouteIs('config.role.new')
                ->with('@form', function (Browser $browser) {
                    $browser
                        ->assertValue('@name_input', 'qwertyuiopasdfghjklzxcvbnmqwertyuiop')
                        ->assertValue('@description_input', 'A fake role for testing');
                });
        });
    }

    public function testRolePermissionToggle(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.role.overview'));
            $browser->pause(250);
            $browser
                ->with('.datatable', function (Browser $browser) {
                    $browser->click('@modify_1');
                });

            $browser
                ->assertRouteIs('config.role.modify', [1])
                ->pause(250);

            $browser->with('.datatable', function (Browser $browser) {
                $browser
                    ->assertSee('Given permission')
                    ->assertSee('Test permission that is given to the test user')

                    ->assertChecked("@permission_checkbox_1")
                    ->assertNotChecked("@permission_checkbox_2")

                    ->uncheck("@permission_checkbox_1")
                    ->check("@permission_checkbox_2");
            });

            $browser
                ->refresh()
                ->pause(250);

            $browser->with('.datatable', function (Browser $browser) {
                $browser
                    ->assertNotChecked("@permission_checkbox_1")
                    ->assertChecked("@permission_checkbox_2");
            });
        });
    }

    public function testDeleteRole(): void
    {

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.role.overview'));
            $browser->pause(250);
            $browser
                ->with('.datatable', function (Browser $browser) {
                    $browser->click('@delete_3');
                });

            $browser->with('#delete_modal', function (Browser $browser) {
                    $browser->disableFitOnFailure();
                    $browser->pause(250);
                    $browser->click("@delete_confirm");
                });

            $browser
                ->assertRouteIs('config.role.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(RoleEnum::ROLE_DELETED_MESSAGE);
                });
        });
    }
}
