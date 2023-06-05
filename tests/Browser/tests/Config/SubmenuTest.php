<?php

namespace Tests\Browser\tests\Config;

use App\Enum\Config\ProjectEnum;
use App\Enum\Config\SubmenuEnum;
use App\Enum\ErrorEnum;
use App\Models\Auth\Permission;
use App\Models\Auth\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SubmenuTest extends DuskTestCase
{
    private const PROJECT_ID = 5;

    public function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ProjectSeeder']);
        $this->artisan('db:seed', ['--class' => 'NavSeeder']);
    }

    public function testOverviewDatatable(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects/modify/' . self::PROJECT_ID);
            $browser->pause(250);
            $browser
                ->with('.datatable tbody', function (Browser $browser) {
                    $browser->scrollIntoView('@modify_3');
                    $browser->pause(250);
                    $browser
                        ->assertSee('Default')
                        ->assertSee('home.show')
                        ->assertSee('1');
                });
        });
    }

    public function testCreateSubmenu(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects/modify/' . self::PROJECT_ID);
            $browser->pause(250);
            $browser
                ->with('.datatable', function (Browser $browser) {
                    $browser->scrollIntoView('@modify_3');
                    $browser->pause(250);
                    $browser->click('@new_submenu');
                });
            $browser
                ->assertRouteIs('config.projects.submenu.new', [self::PROJECT_ID])
                ->with('@form', function (Browser $browser) {
                    $permission_id = Permission::where('permission', 'has.permission')->first()->id;
                    $browser
                        ->type('@name_input', 'Test Project')
                        ->type('@route_input', 'config.projects.overview')
                        ->select('@permission_select', $permission_id)
                        ->type('@order_input', '1')
                        ->click('@submitter');
                })
                ->pause(250)
                ->assertRouteIs('config.projects.modify', [self::PROJECT_ID])
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(SubmenuEnum::SUBMENU_SAVED_MESSAGE);
                });
        });
    }

    public function testUpdateSubmenu(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects/modify/' . self::PROJECT_ID);
            $browser->pause(250);
            $browser
                ->with('.datatable tbody', function (Browser $browser) {
                    $browser->scrollIntoView('@modify_1');
                    $browser->pause(250);
                    $browser->scrollIntoView('@modify_3');
                    $browser->pause(250);
                    $browser->click('@modify_3');
                });
            $browser
                ->assertRouteIs('config.projects.submenu.modify', [self::PROJECT_ID, 3])
                ->with('@form', function (Browser $browser) {
                    $permission_id = Permission::where('permission', 'has.not.permission')->first()->id;
                    $browser
                        ->assertValue('@name_input', 'Invisible')
                        ->assertValue('@route_input', 'home.show')
                        ->select('@permission_select', $permission_id)
                        ->assertValue('@order_input', '3');

                    $browser
                        ->type('@name_input', '123')
                        ->click('@submitter');
                })
                ->pause(250)
                ->assertRouteIs('config.projects.modify', [self::PROJECT_ID])
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(SubmenuEnum::SUBMENU_SAVED_MESSAGE);
                });
        });
    }

    public function testCreateSubmenuOldValues(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects/modify/' . self::PROJECT_ID);
            $browser->pause(250);
            $browser
                ->with('.datatable', function (Browser $browser) {
                    $browser->scrollIntoView('@modify_1');
                    $browser->pause(250);
                    $browser->scrollIntoView('@modify_3');
                    $browser->pause(250);
                    $browser->click('@new_submenu');
                });
            $browser
                ->assertRouteIs('config.projects.submenu.new', [self::PROJECT_ID])
                ->with('@form', function (Browser $browser) {
                    $permission_id = Permission::where('permission', 'has.not.permission')->first()->id;

                    $browser
                        ->type('@name_input', 'Invisible')
                        ->type('@route_input', 'false.show')
                        ->select('@permission_select', $permission_id)
                        ->type('@order_input', '3')
                        ->click('@submitter');

                    $browser->pause(250);

                    $browser
                        ->assertValue('@name_input', 'Invisible')
                        ->select('@permission_select', $permission_id)
                        ->assertValue('@order_input', '3');
                });
        });
    }

    public function testDeleteProject(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects/modify/' . self::PROJECT_ID);
            $browser->pause(250);
            $browser
                ->with('.datatable tbody', function (Browser $browser) {
                    $browser->scrollIntoView('@modify_3');
                    $browser->pause(250);
                    $browser
                        ->click('@delete_3');
                });
                $browser->with('#delete_modal', function (Browser $browser) {
                    $browser->disableFitOnFailure();
                    $browser->pause(250);
                    $browser->click("@delete_confirm");
                });
                $browser->pause(250);
                $browser
                    ->assertRouteIs('config.projects.modify', [self::PROJECT_ID])
                    ->with('@toasts', function (Browser $browser) {
                        $browser->assertSee(SubmenuEnum::SUBMENU_DELETED_MESSAGE);
                    });
        });
    }
}
