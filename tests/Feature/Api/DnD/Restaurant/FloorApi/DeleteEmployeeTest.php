<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

class DeleteEmployeeTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        FileStorage::fake();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
    }

    public function test_deleting_removes_the_employee_and_their_icon(): void
    {
        $employee = $this->createTestEmployee($this->restaurant, null, 'Sam');
        $employee->icon = FileStorage::putFile('modules/dnd/restaurant/employee', UploadedFile::fake()->image('sam.png'));
        $employee->save();

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.floor.employee.delete', [
                'restaurant_uuid' => $this->restaurant->uuid,
                'employee_uuid'   => $employee->uuid,
            ]));

        $response->assertOk();
        $this->assertNull($employee->fresh());
        FileStorage::assertMissing($employee->icon);
        $this->assertEquals([], $response->json('data.floor.employees'));
    }
}
