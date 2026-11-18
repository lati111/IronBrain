<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Room;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

class DeleteRoomTest extends AbstractApiFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Room $room;

    public function setUp(): void
    {
        parent::setUp();
        FileStorage::fake();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->room = $this->createTestRoom($this->restaurant);
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(?Room $room = null): string
    {
        return route('api.dnd.restaurant.floor.room.delete', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'room_uuid'       => ($room ?? $this->room)->uuid,
        ]);
    }

    protected function getModel(): Room
    {
        return new Room();
    }

    public function test_room_is_deleted_and_its_tables_are_kept_unplaced(): void
    {
        FileStorage::put('modules/dnd/restaurant/room/test.jpg', 'image');
        $this->room->background_image = 'modules/dnd/restaurant/room/test.jpg';
        $this->room->save();
        $table = $this->createTestTable($this->restaurant, $this->room);

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute());

        $response->assertOk();
        $this->assertFalse(Room::where('uuid', $this->room->uuid)->exists());
        $this->assertNotNull($table->fresh());
        $this->assertNull($table->fresh()->room_uuid);
        FileStorage::assertMissing('modules/dnd/restaurant/room/test.jpg');
    }

    public function test_room_of_another_restaurant_is_not_found(): void
    {
        $otherRoom = $this->createTestRoom($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($otherRoom));

        $response->assertNotFound();
        $this->assertApiMessage('Room not found', $response);
        $this->assertTrue(Room::where('uuid', $otherRoom->uuid)->exists());
    }
}
