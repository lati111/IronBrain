<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Table;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostEditOperation;
use Tests\Traits\ValidationTests;

class EditTableTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostEditOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->item = $this->createTestTable($this->restaurant, $this->createTestRoom($this->restaurant), 2, 'Window Seat');
    }

    protected function getRoute(?Table $table = null): string
    {
        return route('api.dnd.restaurant.floor.table.edit', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'table_uuid'      => ($table ?? $this->item)->uuid,
        ]);
    }

    protected function getModel(): Table
    {
        return new Table();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'     => 'Long Table',
            'capacity' => 8,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_capacity_validation(): void
    {
        $this->assertIntegerValidation('capacity');
    }

    //| save tests

    public function test_editing_keeps_the_table_in_its_room(): void
    {
        $room = $this->item->room_uuid;

        $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $this->getPostParameters())->assertOk();

        $this->assertEquals($room, $this->item->fresh()->room_uuid);
    }

    public function test_capacity_cannot_drop_below_an_occupied_seat(): void
    {
        $this->createTestCustomer($this->restaurant, $this->item, 'Bilbo', 2);

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), ['capacity' => 1]);

        $response->assertBadRequest();
        $this->assertApiMessage('A customer is seated on a seat this would remove', $response);
        $this->assertEquals(2, $this->item->fresh()->capacity);
    }

    public function test_table_of_another_restaurant_is_not_found(): void
    {
        $otherTable = $this->createTestTable($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($otherTable), $this->getPostParameters());

        $response->assertNotFound();
        $this->assertApiMessage('Table not found', $response);
    }
}
