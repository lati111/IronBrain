<?php

namespace Tests\Unit\Controller\Config;

use App\Enum\Config\ProjectEnum;
use App\Enum\ErrorEnum;
use App\Models\Auth\Permission;
use App\Models\Config\Module;
use Database\Seeders\NavSeeder;
use Database\Seeders\ModuleSeeder;
use Tests\Unit\Controller\AbstractControllerUnitTester;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProjectControllerTest extends AbstractControllerUnitTester
{
    public function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSeeder::class);
        $this->seed(NavSeeder::class);
    }

    //| show overview test
    public function testOverviewShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.projects.overview'));
        $this->assertView($response, 'config.projects.overview');
    }

    //| new project show test
    public function testNewProjectShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.projects.new'));
        $this->assertView($response, 'config.projects.modify');
    }

    //| modify project show tests
    public function testShowModifyProject(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.projects.modify', [$this->getRandomEntity(Module::class)->id]));
        $this->assertView($response, 'config.projects.modify');
    }

    public function testShowModifyProjectNotFound(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.projects.modify', [$this->getFalseId(Module::class)]));
        $this->assertRedirect($response, 'config.projects.overview', [
            'error' => ProjectEnum::PROJECT_NOT_FOUND_MESSAGE
        ]);
    }

    //| save project tests
    public function testSaveNewProjectInOverviewValid(): void
    {
        $route = route('config.projects.save');
        Storage::fake('avatars');

        $routeString = 'home.show';
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $description = $this->faker->regexify('[A-Za-z0-9]{48}');
        $thumbnail = UploadedFile::fake()->image('thumbnail.png');

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' =>  $name,
                'route' => $routeString,
                'description' =>  $description,
                'thumbnail' => $thumbnail,
                'in_overview' => 'on'
            ]);
        $this->assertRedirect($response, 'config.projects.overview', [
            'message' => ProjectEnum::PROJECT_SAVED_MESSAGE
        ]);

        $project = Module::where('name', $name)->where('description', $description)->where('route', $routeString)->first();
        $this->assertNotNull($project);
        $this->assertEquals($name, $project->name);
        $this->assertEquals($routeString, $project->route);
        $this->assertEquals($description, $project->description);
        $this->assertEquals('1', $project->in_overview);
        $this->assertEquals('0', $project->in_nav);

        $this->assertTrue(Storage::exists('project/thumbnail/' . $project->thumbnail));
        Storage::delete('project/thumbnail/' . $project->thumbnail);
    }

    public function testSaveNewProjectInNavValid(): void
    {
        $route = route('config.projects.save');

        $routeString = 'home.show';
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $description = $this->faker->regexify('[A-Za-z0-9]{48}');
        $order = $this->getUniqueOrder();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' =>  $name,
                'route' => $routeString,
                'description' =>  $description,
                'order' => $order,
                'in_nav' => 'on'
            ]);
        $this->assertRedirect($response, 'config.projects.overview', [
            'message' => ProjectEnum::PROJECT_SAVED_MESSAGE
        ]);

        $project = Module::where('name', $name)->where('description', $description)->where('route', $routeString)->first();
        $this->assertNotNull($project);
        $this->assertEquals($name, $project->name);
        $this->assertEquals($routeString, $project->route);
        $this->assertEquals($description, $project->description);
        $this->assertEquals($order, $project->order);
        $this->assertEquals('0', $project->in_overview);
        $this->assertEquals('1', $project->in_nav);
    }

    public function testSaveModifiedProjectInOverviewValid(): void
    {
        $route = route('config.projects.save');
        Storage::fake('avatars');

        $old_project = Module::where('in_overview', true)->first();
        $routeString = 'home.show';
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $description = $this->faker->regexify('[A-Za-z0-9]{48}');
        $thumbnail = UploadedFile::fake()->image('thumbnail.png');

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' => $old_project->id,
                'name' =>  $name,
                'route' => $routeString,
                'description' =>  $description,
                'thumbnail' => $thumbnail,
                'in_overview' => 'on'
            ]);
        $this->assertRedirect($response, 'config.projects.overview', [
            'message' => ProjectEnum::PROJECT_SAVED_MESSAGE
        ]);

        $project = Module::where('name', $name)->where('description', $description)->where('route', $routeString)->first();
        $this->assertNotNull($project);
        $this->assertEquals($name, $project->name);
        $this->assertEquals($routeString, $project->route);
        $this->assertEquals($description, $project->description);
        $this->assertEquals('1', $project->in_overview);
        $this->assertEquals('0', $project->in_nav);

        $this->assertTrue(Storage::exists('project/thumbnail/' . $project->thumbnail));
        Storage::delete('project/thumbnail/' . $project->thumbnail);
    }

    public function testSaveModifiedProjectInNavValid(): void
    {
        $route = route('config.projects.save');

        $old_project = Module::where('in_nav', true)->first();
        $routeString = 'home.show';
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $description = $this->faker->regexify('[A-Za-z0-9]{48}');
        $order = $this->getUniqueOrder();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' => $old_project->id,
                'name' =>  $name,
                'route' => $routeString,
                'description' =>  $description,
                'order' => $order,
                'in_nav' => 'on'
            ]);
        $this->assertRedirect($response, 'config.projects.overview', [
            'message' => ProjectEnum::PROJECT_SAVED_MESSAGE
        ]);

        $project = Module::where('name', $name)->where('description', $description)->where('route', $routeString)->first();
        $this->assertNotNull($project);
        $this->assertEquals($name, $project->name);
        $this->assertEquals($routeString, $project->route);
        $this->assertEquals($description, $project->description);
        $this->assertEquals($order, $project->order);
        $this->assertEquals('0', $project->in_overview);
        $this->assertEquals('1', $project->in_nav);
    }

    public function testSaveProjectIdValidation(): void
    {
        $route = route('config.projects.save');
        $project = $this->getRandomEntity(Module::class);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' =>  $project->id,
            ]);
        $this->assertValidationValid('id');

        //exists
        $this->post($route, [
            'id' => $this->getFalseId(Module::class)
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

    public function testSaveProjectThumbnailValidation(): void
    {
        Storage::fake('avatars');
        $route = route('config.projects.save');

        //is nullable
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationValid('thumbnail');

        //is image filetype
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'thumbnail' => UploadedFile::fake()->create('thumbnail.csv'),
            ]);
        $this->assertValidationImageFileType('thumbnail');
    }

    public function testSaveProjectNameValidation(): void
    {
        $route = route('config.projects.save');

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

    public function testSaveProjectRouteValidation(): void
    {
        $route = route('config.projects.save');

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'route' =>  $this->faker->regexify('[A-Za-z0-9]{56}'),
            ]);
        $this->assertValidationValid('route');

        //nullable
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationValid('route');

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

    public function testSaveProjectDescriptionValidation(): void
    {
        $route = route('config.permission.save');

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'description' =>  $this->faker->regexify('[A-Za-z0-9]{48}'),
            ]);
        $this->assertValidationValid('description');

        //required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('description');

        //is string
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'description' => 44,
            ]);
        $this->assertValidationString('description');
    }

    public function testSaveProjectPermissionIdValidation(): void
    {
        $route = route('config.projects.save');
        $project = $this->getRandomEntity(Permission::class);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'permission_id' =>  $project->id,
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

    public function testSaveProjectOrderValidation(): void
    {
        $route = route('config.projects.save');

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'order' =>  $this->getUniqueOrder(),
            ]);
        $this->assertValidationValid('order');

        //is nullable
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationValid('order');

        //is integer
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'order' => "test",
            ]);
        $this->assertValidationInteger('order');
    }

    public function testSaveProjectInvalidRoute(): void
    {
        $route = route('config.projects.save');

        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' =>  $this->faker->regexify('[A-Za-z0-9]{26}'),
                'route' => 'false.route',
                'description' =>  $this->faker->regexify('[A-Za-z0-9]{48}'),
                'in_overview' => 'on'
            ]);
        $this->assertEquals(ErrorEnum::INVALID_ROUTE_MESSAGE, session('error'));
    }

    public function testSaveProjectThumbnailNotSet(): void
    {
        $route = route('config.projects.save');

        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' =>  $this->faker->regexify('[A-Za-z0-9]{26}'),
                'route' => 'home.show',
                'description' =>  $this->faker->regexify('[A-Za-z0-9]{48}'),
                'in_overview' => 'on'
            ]);
        $this->assertEquals(ProjectEnum::MISSING_THUMBNAIL_MESSAGE, session('error'));
    }

    //| delete project tests
    public function testDeleteProjectValid(): void
    {
        $permission = $this->createRandomEntity(Module::class);
        $route = route('config.projects.delete', $permission->id);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertNull(Permission::where('id', $permission->id)->first());
        $this->assertRedirect($response, 'config.projects.overview', [
            "message" => ProjectEnum::PROJECT_DELETED_MESSAGE,
        ]);
    }

    public function testDeleteProjectNotFound(): void
    {
        $route = route('config.projects.delete', $this->getFalseId(Module::class));

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertRedirect($response, 'config.projects.overview', [
            "error" => ProjectEnum::PROJECT_NOT_FOUND_MESSAGE,
        ]);
    }


    //| getters
    private function getUniqueOrder(): int
    {
        $order = null;
        while ($order === null) {
            $int = $this->faker->randomNumber();
            if (Module::where('order', $int)->count() === 0) {
                $order = $int;
            }
        }

        return $order;
    }
}
