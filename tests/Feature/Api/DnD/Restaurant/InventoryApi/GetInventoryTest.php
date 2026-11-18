<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

class GetInventoryTest extends AbstractApiFeatureTester
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
        return route('api.dnd.restaurant.inventory.get', ['restaurant_uuid' => ($restaurant ?? $this->restaurant)->uuid]);
    }

    protected function getModel(): Restaurant
    {
        return new Restaurant();
    }

    public function test_returns_storages_in_order_with_contents_and_totals(): void
    {
        $second = $this->createTestStorage($this->restaurant, 50, 1, 'Pantry');
        $first  = $this->createTestStorage($this->restaurant, 20, 0, 'Cellar');
        $potato = $this->createTestIngredient($this->restaurant, 2, 'Potato');
        $pot    = $this->createTestEquipment($this->restaurant, 3, 'Pot');

        $this->storeIngredient($first, $potato, 4);
        $this->storeIngredient($second, $potato, 1);
        $this->storeEquipment($first, $pot, 1);

        $response = $this->getHttpClient($this->getOperationUser())->get($this->getRoute());

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals(['Cellar', 'Pantry'], array_column($data['storages'], 'name'));
        $this->assertEquals(11, $data['storages'][0]['used_capacity']);
        $this->assertCount(2, $data['storages'][0]['items']);
        $this->assertEquals(5, $data['ingredients'][0]['stored']);
        $this->assertEquals(1, $data['equipment'][0]['stored']);
    }

    public function test_equipment_that_does_not_require_storage_reports_the_owned_amount(): void
    {
        $tool = $this->createTestEquipment($this->restaurant, 1, 'Ladle', false);
        $this->ownEquipment($this->restaurant, $tool, 3);

        $response = $this->getHttpClient($this->getOperationUser())->get($this->getRoute());

        $response->assertOk();
        $equipment = $response->json('data.equipment.0');
        $this->assertFalse($equipment['requires_storage']);
        $this->assertEquals(3, $equipment['stored']);
    }

    public function test_shared_users_can_view_the_inventory(): void
    {
        $user = $this->getRandomUser(['dnd_restaurant.access']);
        $this->grantRestaurantAccess($this->restaurant, $user);

        $response = $this->withHeaders($this->getDefaultHeaders())
            ->actingAs($user)
            ->get($this->getRoute());

        $response->assertOk();
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->get($this->getRoute($restaurant));

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
