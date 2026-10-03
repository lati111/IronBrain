<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostEditOperation;
use Tests\Traits\ValidationTests;

class EditEmployeeTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostEditOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->item = $this->createTestEmployee($this->restaurant, null, 'Sam');
    }

    protected function getRoute(?Employee $employee = null): string
    {
        return route('api.dnd.restaurant.floor.employee.edit', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'employee_uuid'   => ($employee ?? $this->item)->uuid,
        ]);
    }

    protected function getModel(): Employee
    {
        return new Employee();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'           => 'Samwise Gamgee',
            'prep_speed'     => 2,
            'cleaning_speed' => 0.5,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertMaxValidation('string', 'name', 64);
    }

    //| save tests

    public function test_editing_keeps_the_employee_at_work(): void
    {
        $this->item->station = WorkStation::PREP;
        $this->item->save();

        $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), ['prep_speed' => 3])->assertOk();

        $employee = $this->item->fresh();
        $this->assertEquals(WorkStation::PREP, $employee->station);
        $this->assertEquals(3.0, $employee->prep_speed);
        $this->assertEquals('Sam', $employee->name);
        $this->assertEquals(1.0, $employee->cleaning_speed);
    }

    public function test_employee_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestEmployee($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($other), $this->getPostParameters());

        $response->assertNotFound();
        $this->assertApiMessage('Employee not found', $response);
    }
}
