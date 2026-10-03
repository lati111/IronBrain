<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

class GetFloorTest extends AbstractApiFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
    }

    protected function getOperationType(): string
    {
        return 'GET';
    }

    protected function getRoute(?Restaurant $restaurant = null): string
    {
        return route('api.dnd.restaurant.floor.get', ['restaurant_uuid' => ($restaurant ?? $this->restaurant)->uuid]);
    }

    protected function getModel(): Restaurant
    {
        return new Restaurant();
    }

    public function test_returns_rooms_in_order_with_tables_and_customers(): void
    {
        $patio = $this->createTestRoom($this->restaurant, 1, 'Patio');
        $hall  = $this->createTestRoom($this->restaurant, 0, 'Hall');
        $booth = $this->createTestTable($this->restaurant, $hall, 4, 'Booth');
        $this->createTestTable($this->restaurant, null, 2, 'Spare');
        $this->createTestCustomer($this->restaurant, $booth, 'Bilbo');
        $this->createTestCustomer($this->restaurant, null, 'Frodo');

        $response = $this->getHttpClient($this->getOperationUser())->get($this->getRoute());

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals(['Hall', 'Patio'], array_column($data['rooms'], 'name'));
        $this->assertEquals(['Booth', 'Spare'], array_column($data['tables'], 'name'));
        $this->assertEquals($hall->uuid, $data['tables'][0]['room_uuid']);
        $this->assertNull($data['tables'][1]['room_uuid']);
        $this->assertEquals(1, $data['tables'][0]['seated']);
        $this->assertEquals($booth->uuid, $data['customers'][0]['table_uuid']);
        $this->assertNull($data['customers'][1]['table_uuid']);
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->get($this->getRoute($restaurant));

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
