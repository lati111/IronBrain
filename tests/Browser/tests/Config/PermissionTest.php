<?php

namespace Tests\Browser\tests\Config;

use App\Enum\Auth\PermissionEnum;
use App\Models\Auth\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PermissionTest extends DuskTestCase
{
    public function testOverviewDatatable(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.permission.overview'))
                ->with('.datatable tbody', function (Browser $browser) {
                    $browser->pause(250);
                    $browser
                        ->assertSee('has.permission')
                        ->assertSee('Given permission')
                        ->assertSee('Test permission that is given to the test user')
                        ->assertSee('test');
                });
        });
    }

    public function testCreatePermission(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.permission.overview'))
                ->click('@new_permission')
                ->assertRouteIs('config.permission.new')

                ->with('@form', function (Browser $browser) {
                    $browser
                        ->type('@name_input', 'Dummy Permission')
                        ->type('@permission_input', 'dummy.permission')
                        ->type('@group_input', 'test')
                        ->type('@description_input', 'A fake permission for testing')
                        ->click('@submitter');
                })
                ->assertRouteIs('config.permission.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(PermissionEnum::SAVED_MESSAGE);
                });
        });
    }

    public function testUpdatePermission(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.permission.overview'));
            $browser->pause(250);
            $browser
                ->with('.datatable', function (Browser $browser) {
                    $browser->click('@modify_2');
                });
            $browser->assertRouteIs('config.permission.modify', [2]);

            $browser
                ->with('@form', function (Browser $browser) {
                    $browser
                        ->assertValue('@name_input', 'Ungiven permission')
                        ->assertValue('@permission_input', 'has.not.permission')
                        ->assertValue('@group_input', 'test')
                        ->assertValue('@description_input', 'Test permission that is not given to the test user')

                        ->type('@name_input', '123')
                        ->click('@submitter');
                })
                ->assertRouteIs('config.permission.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(PermissionEnum::SAVED_MESSAGE);
                });
        });
    }

    public function testPermissionModifyOldValues(): void
    {
        $this->browse(function (Browser $browser) {
            $permission = "dummy.permission";
            $description = 'A fake permission for testing';
            $group = 'test';

            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.permission.overview'))
                ->click('@new_permission')
                ->assertRouteIs('config.permission.new')

                ->with('@form', function (Browser $browser) use ($permission, $description, $group) {
                    $browser
                        ->type('@name_input', 'jGz6bx9iimvqqDXiteTUtbbM2ssvLyaEEnX390W2y2itzTMM7r')
                        ->type('@permission_input', $permission)
                        ->type('@group_input', $group)
                        ->type('@description_input', $description)
                        ->click('@submitter');
                });

            $browser->pause(250);

            $browser
                ->assertRouteIs('config.permission.new')
                ->with('@form', function (Browser $browser) use ($permission, $description, $group) {
                    $browser
                        ->assertValue('@permission_input', $permission)
                        ->assertValue('@description_input', $description)
                        ->assertValue('@group_input', $group);
                });
        });
    }

    public function testDeletePermission(): void
    {

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit(route('config.permission.overview'));
            $browser->pause(250);
            $browser
                ->with('.datatable', function (Browser $browser) {
                    $browser->click('@delete_2');
                });

            $browser->with('#delete_modal', function (Browser $browser) {
                    $browser->disableFitOnFailure();
                    $browser->pause(250);
                    $browser->click("@delete_confirm");
                });

            $browser
                ->assertRouteIs('config.permission.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(PermissionEnum::DELETED_MESSAGE);
                });
        });
    }
}
