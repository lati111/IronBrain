<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Room;
use App\Models\DnD\Restaurant\Table;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class MoveTableTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;
    private Room $room;
    private Table $table;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->room = $this->createTestRoom($this->restaurant);
        $this->table = $this->createTestTable($this->restaurant);
    }

    protected function getRoute(?Table $table = null): string
    {
        return route('api.dnd.restaurant.floor.table.move', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'table_uuid'      => ($table ?? $this->table)->uuid,
        ]);
    }

    protected function getModel(): Table
    {
        return new Table();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'room_uuid' => $this->room->uuid,
        ], $overwrites);
    }

    public function test_table_is_placed_in_a_room(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $this->getPostParameters());

        $response->assertOk();
        $this->assertApiMessage('Table placed', $response);
        $this->assertEquals($this->room->uuid, $this->table->fresh()->room_uuid);
    }

    public function test_table_is_moved_between_rooms(): void
    {
        $patio = $this->createTestRoom($this->restaurant, 1, 'Patio');
        $this->table->room_uuid = $this->room->uuid;
        $this->table->save();

        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['room_uuid' => $patio->uuid])
            ->assertOk();

        $this->assertEquals($patio->uuid, $this->table->fresh()->room_uuid);
    }

    public function test_table_is_taken_out_of_its_room_without_a_room(): void
    {
        $this->table->room_uuid = $this->room->uuid;
        $this->table->save();

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), []);

        $response->assertOk();
        $this->assertApiMessage('Table removed from room', $response);
        $this->assertNull($this->table->fresh()->room_uuid);
    }

    public function test_room_of_another_restaurant_is_not_found(): void
    {
        $otherRoom = $this->createTestRoom($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['room_uuid' => $otherRoom->uuid]);

        $response->assertNotFound();
        $this->assertApiMessage('Room not found', $response);
        $this->assertNull($this->table->fresh()->room_uuid);
    }

    public function test_table_of_another_restaurant_is_not_found(): void
    {
        $otherTable = $this->createTestTable($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($otherTable), $this->getPostParameters());

        $response->assertNotFound();
        $this->assertApiMessage('Table not found', $response);
    }
}
