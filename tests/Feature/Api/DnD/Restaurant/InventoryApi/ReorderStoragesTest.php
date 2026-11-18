<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostOperation;

class ReorderStoragesTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, RestaurantTestHelper;

    private Restaurant $restaurant;
    private Storage $first;
    private Storage $second;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->first      = $this->createTestStorage($this->restaurant, 10, 0, 'First');
        $this->second     = $this->createTestStorage($this->restaurant, 10, 1, 'Second');
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.inventory.storage.reorder', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Storage
    {
        return new Storage();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'order' => [$this->second->uuid, $this->first->uuid],
        ], $overwrites);
    }

    public function test_storages_are_reordered(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters());

        $response->assertOk();
        $this->assertEquals(0, $this->second->fresh()->order);
        $this->assertEquals(1, $this->first->fresh()->order);
        $this->assertEquals(['Second', 'First'], array_column($response->json('data.inventory.storages'), 'name'));
    }

    public function test_order_must_contain_every_storage(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['order' => [$this->second->uuid]]);

        $response->assertBadRequest();
        $this->assertApiMessage('The order must contain every storage space exactly once', $response);
        $this->assertEquals(0, $this->first->fresh()->order);
    }

    public function test_order_cannot_contain_foreign_storages(): void
    {
        $other = $this->createTestStorage($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['order' => [$this->second->uuid, $this->first->uuid, $other->uuid]]);

        $response->assertBadRequest();
    }
}
