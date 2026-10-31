<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Table;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateTableTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.floor.table.create', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Table
    {
        return new Table();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'     => 'Corner Booth',
            'capacity' => 4,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_capacity_validation(): void
    {
        $this->assertRequiredValidation('capacity');
        $this->assertIntegerValidation('capacity');
    }

    public function test_capacity_must_be_at_least_one(): void
    {
        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['capacity' => 0]))
            ->assertBadRequest();
    }

    //| save tests

    public function test_table_is_created_unplaced(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $this->getPostParameters());

        $response->assertCreated();
        $table = Table::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals(4, $table->capacity);
        $this->assertNull($table->room_uuid);
    }

    public function test_table_can_be_created_in_a_room(): void
    {
        $room = $this->createTestRoom($this->restaurant);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['room_uuid' => $room->uuid]));

        $response->assertCreated();
        $this->assertEquals($room->uuid, Table::where('uuid', $response->json('data.uuid'))->value('room_uuid'));
    }

    public function test_room_of_another_restaurant_is_not_found(): void
    {
        $otherRoom = $this->createTestRoom($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['room_uuid' => $otherRoom->uuid]));

        $response->assertNotFound();
        $this->assertApiMessage('Room not found', $response);
    }
}
