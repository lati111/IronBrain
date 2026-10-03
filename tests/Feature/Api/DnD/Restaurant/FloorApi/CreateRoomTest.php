<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Room;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateRoomTest extends AbstractApiFeatureTester implements PostOperationInterface
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
        return route('api.dnd.restaurant.floor.room.create', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Room
    {
        return new Room();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name' => 'Common Room',
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertMaxValidation('string', 'name', 64);
    }

    //| save tests

    public function test_room_is_added_at_the_end_with_background(): void
    {
        $this->createTestRoom($this->restaurant, 3, 'Cellar');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'background_image' => UploadedFile::fake()->image('room.png'),
            ]));

        $response->assertCreated();
        $room = Room::where('restaurant_uuid', $this->restaurant->uuid)->where('name', 'Common Room')->first();
        $this->assertEquals(4, $room->order);
        $this->assertStringStartsWith('modules/dnd/restaurant/room/', $room->background_image);
        FileStorage::assertExists($room->background_image);
        $this->assertEquals(['Cellar', 'Common Room'], array_column($response->json('data.floor.rooms'), 'name'));
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.floor.room.create', ['restaurant_uuid' => $restaurant->uuid]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
