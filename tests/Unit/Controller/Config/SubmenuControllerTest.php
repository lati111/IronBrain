<?php

namespace Tests\Unit\Controller\Config;

use App\Enum\Config\ProjectEnum;
use App\Enum\Config\SubmenuEnum;
use App\Enum\ErrorEnum;
use App\Models\Auth\Permission;
use App\Models\Config\Module;
use App\Models\Config\Submodule;
use Database\Seeders\NavSeeder;
use Tests\Unit\Controller\AbstractControllerUnitTester;

class SubmenuControllerTest extends AbstractControllerUnitTester
{
    public function setUp(): void
    {
        parent::setUp();
        $this->seed(NavSeeder::class);
    }

    //| new submenu show test
    public function testShowNewSubmenu(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.projects.submenu.new', [$project->id]));
        $this->assertView($response, 'config.projects.submenu.modify');
    }

    //| modify submenu show tests
    public function testShowModifySubmenu(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.projects.submenu.modify', [
                $project->id,
                $project->submodules()->first()
            ]));
        $this->assertView($response, 'config.projects.submenu.modify');
    }

    public function testShowModifySubmenuProjectNotFound(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.projects.submenu.modify', [
                $this->getFalseId(Module::class),
                $this->getFalseId(Submodule::class)
            ]));
        $this->assertRedirect($response, 'config.projects.overview', [
            'error' => ProjectEnum::PROJECT_NOT_FOUND_MESSAGE
        ]);
    }

    public function testShowModifySubmenuNotFound(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.projects.submenu.modify', [
                $project->id,
                $this->getFalseId(Submodule::class)
            ]));
        $this->assertRedirectWithRouteParams($response, 'config.projects.modify', [$project->id], [
            'error' => SubmenuEnum::SUBMENU_NOT_FOUND_MESSAGE
        ]);
    }

    //| save project tests
    public function testSaveNewSubmenuValid(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $route = route('config.projects.submenu.save', [$project->id]);

        $routeString = 'home.show';
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $permission = $this->getRandomEntity(Permission::class);
        $order = $this->getUniqueOrder();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' =>  $name,
                'route' => $routeString,
                'permission_id' =>  $permission->id,
                'order' => $order,
            ]);
        $this->assertRedirectWithRouteParams($response, 'config.projects.modify', [$project->id], [
            'message' => SubmenuEnum::SUBMENU_SAVED_MESSAGE
        ]);

        $submenu = Submodule::where('order', $order)->first();
        $this->assertNotNull($submenu);
        $this->assertEquals($project->id, $submenu->project_id);
        $this->assertEquals($name, $submenu->name);
        $this->assertEquals($routeString, $submenu->route);
        $this->assertEquals($permission->id, $submenu->permission_id);
        $this->assertEquals($order, $submenu->order);
    }

    public function testSaveModifiedSubmenuValid(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $submenu = $project->submodules()->first();
        $route = route('config.projects.submenu.save', [$project->id]);

        $routeString = 'home.show';
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $permission = $this->getRandomEntity(Permission::class);
        $order = $this->getUniqueOrder();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' => $submenu->id,
                'name' =>  $name,
                'route' => $routeString,
                'permission_id' =>  $permission->id,
                'order' => $order,
            ]);
        $this->assertRedirectWithRouteParams($response, 'config.projects.modify', [$project->id], [
            'message' => SubmenuEnum::SUBMENU_SAVED_MESSAGE
        ]);

        $submenu = Submodule::where('id', $submenu->id)->first();
        $this->assertNotNull($submenu);
        $this->assertEquals($project->id, $submenu->project_id);
        $this->assertEquals($name, $submenu->name);
        $this->assertEquals($routeString, $submenu->route);
        $this->assertEquals($permission->id, $submenu->permission_id);
        $this->assertEquals($order, $submenu->order);
    }

    public function testSaveSubmenuIdValidation(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $route = route('config.projects.submenu.save', [$project->id]);
        $submenu = $this->getRandomEntity(Submodule::class);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' =>  $submenu->id,
            ]);
        $this->assertValidationValid('id');

        //exists
        $this->post($route, [
            'id' => $this->getFalseId(Submodule::class)
        ]);
        $this->assertValidationExists('id');

        //is nullable
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationValid('id');

        //is integer
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' => "test",
            ]);
        $this->assertValidationInteger('id');
    }

    public function testSaveSubmenuNameValidation(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $route = route('config.projects.submenu.save', [$project->id]);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' =>  $this->faker->regexify('[A-Za-z0-9]{26}'),
            ]);
        $this->assertValidationValid('name');

        //required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('name');

        //too long
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' => $this->faker->regexify('[A-Za-z0-9]{65}'),
            ]);
        $this->assertValidationTooLong('name', 64);

        //is string
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' => 44,
            ]);
        $this->assertValidationString('name');
    }

    public function testSaveSubmenuRouteValidation(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $route = route('config.projects.submenu.save', [$project->id]);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'route' =>  $this->faker->regexify('[A-Za-z0-9]{56}'),
            ]);
        $this->assertValidationValid('route');

        //required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('route');

        //too long
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'route' => $this->faker->regexify('[A-Za-z0-9]{260}'),
            ]);
        $this->assertValidationTooLong('route', 255);

        //is string
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'route' => 44,
            ]);
        $this->assertValidationString('route');
    }

    public function testSaveSubmenuPermissionIdValidation(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $route = route('config.projects.submenu.save', [$project->id]);
        $permission = $this->getRandomEntity(Permission::class);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'permission_id' =>  $permission->id,
            ]);
        $this->assertValidationValid('permission_id');

        //exists
        $this->post($route, [
            'permission_id' => $this->getFalseId(Permission::class)
        ]);
        $this->assertValidationExists('permission_id');

        //is nullable
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationValid('permission_id');

        //is integer
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'permission_id' => "test",
            ]);
        $this->assertValidationInteger('permission_id');
    }

    public function testSaveSubmenuOrderValidation(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $route = route('config.projects.submenu.save', [$project->id]);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'order' =>  $this->getUniqueOrder(),
            ]);
        $this->assertValidationValid('order');

        //required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('order');

        //is integer
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'order' => "test",
            ]);
        $this->assertValidationInteger('order');
    }

    public function testSaveSubmenuInvalidRoute(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $route = route('config.projects.submenu.save', [$project->id]);

        $routeString = 'false.route';
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $permission = $this->getRandomEntity(Permission::class);
        $order = $this->getUniqueOrder();

        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' =>  $name,
                'route' => $routeString,
                'permission_id' =>  $permission->id,
                'order' => $order,
            ]);
        $this->assertEquals(ErrorEnum::INVALID_ROUTE_MESSAGE, session('error'));
    }

    // //| delete project tests
    public function testDeleteSubmenuValid(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $submenu = $project->submodules()->first();
        $route = route('config.projects.submenu.delete', [$project->id, $submenu->id,]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertNull(Submodule::where('id', $submenu->id)->first());
        $this->assertRedirectWithRouteParams($response, 'config.projects.modify', [$project->id], [
            'message' => SubmenuEnum::SUBMENU_DELETED_MESSAGE
        ]);
    }

    public function testDeleteSubmenuNotFound(): void
    {
        $project = $this->getRandomProjectWithSubmenu();
        $route = route('config.projects.submenu.delete', [$project->id, $this->getFalseId(Submodule::class)]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);
            $this->assertRedirectWithRouteParams($response, 'config.projects.modify', [$project->id], [
                "error" => SubmenuEnum::SUBMENU_NOT_FOUND_MESSAGE,
        ]);
    }


    //| getters
    private function getRandomProjectWithSubmenu(): Module
    {
        $submenu = $this->getRandomEntity(Submodule::class);
        return $submenu->module()->first();
    }

    private function getUniqueOrder(): int
    {
        $order = null;
        while ($order === null) {
            $int = $this->faker->randomNumber();
            if (Submodule::where('order', $int)->count() === 0) {
                $order = $int;
            }
        }

        return $order;
    }
}
