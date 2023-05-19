<?php

namespace Tests\Browser\tests\Config;

use App\Enum\Config\ProjectEnum;
use App\Enum\ErrorEnum;
use App\Models\Auth\Permission;
use App\Models\Auth\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProjectTest extends DuskTestCase
{
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
                ->visit('/config/projects')
                ->with('.datatable tbody', function (Browser $browser) {
                    $browser->pause(250);
                    $browser
                        ->assertSee('Default Test Project')
                        ->assertSee('A test to see if a project is displayed in the overview when the user has no permissions')
                        ->assertSourceHas('http://127.0.0.1:8000/img/project/thumbnail/test.png')
                        ->assertSee('home.show')
                        ->assertSee('99');
                });
        });
    }

    public function testCreateOverviewProject(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects')
                ->click('@add_project')
                ->assertRouteIs('config.projects.new')
                ->with('@multi-form', function (Browser $browser) {
                    $permission_id = Permission::where('permission', 'has.permission')->first()->id;
                    $browser
                        ->type('@name_input', 'Test Project')
                        ->type('@route_input', 'config.projects.overview')
                        ->type('@description_field', 'A test description')
                        ->select('@permission_select', $permission_id)
                        ->attach('@image_uploader', __DIR__ . '/../../../Data/Img/test.png')
                        ->click('@submitter');
                })
                ->assertRouteIs('config.projects.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(ProjectEnum::PROJECT_SAVED_MESSAGE);
                });
        });
    }

    public function testUpdateOverviewProject(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects');
            $browser->pause(500);
            $browser
                ->click('@modify_2')
                ->assertRouteIs('config.projects.modify', [2])
                ->with('@multi-form', function (Browser $browser) {
                    $browser->disableFitOnFailure();
                    $permission_id = (string) Permission::where('permission', 'has.permission')->first()->id;
                    $browser->pause(250);
                    $browser
                        ->assertValue('@name_input', 'Visible Test Project')
                        ->assertValue('@route_input', 'home.show')
                        ->assertSelected('@permission_select', $permission_id)
                        ->assertValue(
                            '@description_field',
                            'A test to see if a project is displayed in the overview when the user has the right permissions'
                        )
                        ->assertSourceHas('http://127.0.0.1:8000/img/project/thumbnail/test.png')
                        ->scrollIntoView('#submitter')
                        ->assertChecked('@in_overview_check')
                        ->assertNotChecked('@in_nav_check');

                    $browser
                        ->type('@name_input', '123')
                        ->click('@submitter');
                });
            $browser->pause(250);
            $browser
                ->assertRouteIs('config.projects.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(ProjectEnum::PROJECT_SAVED_MESSAGE);
                });
        });
    }

    public function testCreateNavProject(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects')
                ->click('@add_project')
                ->assertRouteIs('config.projects.new')
                ->with('@multi-form', function (Browser $browser) {
                    $browser->disableFitOnFailure();
                    $permission_id = Permission::where('permission', 'has.permission')->first()->id;
                    $browser
                        ->type('@name_input', 'Test Project')
                        ->type('@route_input', 'config.projects.overview')
                        ->select('@permission_select', $permission_id)
                        ->type('@description_field', 'A test description')
                        ->scrollIntoView('#submitter')
                        ->check('@in_nav_check')
                        ->uncheck('@in_overview_check')
                        ->type('@order_input', '-1')
                        ->scrollIntoView('#submitter')
                        ->click('@submitter');
                })
                ->assertRouteIs('config.projects.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(ProjectEnum::PROJECT_SAVED_MESSAGE);
                });
        });
    }

    public function testUpdateNavProject(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects');
            $browser->pause(500);
            $browser
                ->click('@modify_5')
                ->assertRouteIs('config.projects.modify', [5])
                ->with('@multi-form', function (Browser $browser) {
                    $browser->disableFitOnFailure();
                    $permission_id = (string) Permission::where('permission', 'has.permission')->first()->id;
                    $browser->pause(250);
                    $browser
                        ->assertValue('@name_input', 'Config')
                        ->assertValue('@route_input', 'home.show')
                        ->assertSelected('@permission_select', $permission_id)
                        ->assertValue(
                            '@description_field',
                            'A test to see if a project is displayed in the nav when the user has the right permissions'
                        )
                        ->assertValue('@order_input', '99')
                        ->scrollIntoView('#submitter')
                        ->assertNotChecked('@in_overview_check')
                        ->assertChecked('@in_nav_check');

                    $browser
                        ->type('@name_input', '123')
                        ->click('@submitter');
                });
            $browser->pause(250);
            $browser
                ->assertRouteIs('config.projects.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(ProjectEnum::PROJECT_SAVED_MESSAGE);
                });
        });
    }

    public function testCreateProjectOldValues(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects')
                ->click('@add_project')
                ->assertRouteIs('config.projects.new')
                ->with('@multi-form', function (Browser $browser) {
                    $browser->disableFitOnFailure();
                    $permission_id = Permission::where('permission', 'has.permission')->first()->id;
                    $browser
                        ->type('@name_input', 'Test Project')
                        ->type('@route_input', 'false.route')
                        ->select('@permission_select', $permission_id)
                        ->type('@description_field', 'A test description')
                        ->scrollIntoView('#submitter')
                        ->check('@in_nav_check')
                        ->uncheck('@in_overview_check')
                        ->type('@order_input', '-1')
                        ->scrollIntoView('#submitter')
                        ->click('@submitter');
                })
                ->assertRouteIs('config.projects.new')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(ErrorEnum::INVALID_ROUTE_MESSAGE);
                })
                ->with('@multi-form', function (Browser $browser) {
                    $browser->disableFitOnFailure();
                    $permission_id = (string) Permission::where('permission', 'has.permission')->first()->id;
                    $browser->pause(250);
                    $browser
                        ->assertValue('@name_input', 'Test Project')
                        ->assertValue('@route_input', 'false.route')
                        ->assertSelected('@permission_select', $permission_id)
                        ->assertValue('@description_field', 'A test description')
                        ->scrollIntoView('#submitter')
                        ->assertChecked('@in_nav_check')
                        ->assertNotChecked('@in_overview_check')
                        ->assertValue('@order_input', '-1');
                });
        });
    }

    public function testDeleteProject(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::where('name', 'Tester')->first())
                ->visit('/config/projects');
            $browser->pause(500);
            $browser
                ->click('@delete_3')
                ->with('#delete_modal', function (Browser $browser) {
                    $browser->disableFitOnFailure();
                    $browser->pause(250);
                    $browser->click("@delete_confirm");
                });
            $browser->pause(250);
            $browser
                ->assertRouteIs('config.projects.overview')
                ->with('@toasts', function (Browser $browser) {
                    $browser->assertSee(ProjectEnum::PROJECT_DELETED_MESSAGE);
                });
        });
    }
}
