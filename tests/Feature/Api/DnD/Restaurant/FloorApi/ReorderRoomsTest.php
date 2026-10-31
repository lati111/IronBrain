<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Room;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class ReorderRoomsTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;
    private Room $first;
    private Room $second;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->first  = $this->createTestRoom($this->restaurant, 0, 'Hall');
        $this->second = $this->createTestRoom($this->restaurant, 1, 'Patio');
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.floor.room.reorder', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Room
    {
        return new Room();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'order' => [$this->second->uuid, $this->first->uuid],
        ], $overwrites);
    }

    public function test_order_validation(): void
    {
        $this->assertRequiredValidation('order');
    }

    public function test_rooms_are_reordered(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $this->getPostParameters());

        $response->assertOk();
        $this->assertEquals(0, $this->second->fresh()->order);
        $this->assertEquals(1, $this->first->fresh()->order);
        $this->assertEquals(['Patio', 'Hall'], array_column($response->json('data.floor.rooms'), 'name'));
    }

    public function test_order_must_contain_every_room_once(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['order' => [$this->first->uuid, $this->first->uuid]]);

        $response->assertBadRequest();
        $this->assertApiMessage('The order must contain every room exactly once', $response);
    }
}
