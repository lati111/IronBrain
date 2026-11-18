<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateEmployeeTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        FileStorage::fake();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.floor.employee.create', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Employee
    {
        return new Employee();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'           => 'Samwise',
            'prep_speed'     => 1.5,
            'cleaning_speed' => 0.75,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_speeds_cannot_be_negative(): void
    {
        foreach (['prep_speed', 'cleaning_speed'] as $field) {
            $this->getHttpClient($this->getOperationUser())
                ->post($this->getRoute(), $this->getPostParameters([$field => -1]))
                ->assertBadRequest();
        }
    }

    //| save tests

    public function test_employee_is_created_with_an_icon(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['icon' => UploadedFile::fake()->image('sam.png')]));

        $response->assertCreated();
        $employee = Employee::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals(1.5, $employee->prep_speed);
        $this->assertEquals(0.75, $employee->cleaning_speed);
        $this->assertNull($employee->station);
        $this->assertStringStartsWith('modules/dnd/restaurant/employee/', $employee->icon);
        FileStorage::assertExists($employee->icon);
        $this->assertEquals('Samwise', $response->json('data.floor.employees.0.name'));
    }

    public function test_speeds_default_to_normal(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['name' => 'Rosie']);

        $response->assertCreated();
        $employee = Employee::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals(1.0, $employee->prep_speed);
        $this->assertEquals(1.0, $employee->cleaning_speed);
    }
}
