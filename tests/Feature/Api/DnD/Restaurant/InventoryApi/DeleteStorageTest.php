<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageIngredient;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

class DeleteStorageTest extends AbstractApiFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Storage $storage;
    private Ingredient $ingredient;

    public function setUp(): void
    {
        parent::setUp();
        FileStorage::fake();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->storage    = $this->createTestStorage($this->restaurant);
        $this->ingredient = $this->createTestIngredient($this->restaurant);
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(?Storage $storage = null): string
    {
        return route('api.dnd.restaurant.inventory.storage.delete', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'storage_uuid'    => ($storage ?? $this->storage)->uuid,
        ]);
    }

    protected function getModel(): Storage
    {
        return new Storage();
    }

    public function test_storage_is_deleted_and_contents_are_released(): void
    {
        $this->storeIngredient($this->storage, $this->ingredient, 3);
        FileStorage::put('modules/dnd/restaurant/storage/test.jpg', 'image');
        $this->storage->background_image = 'modules/dnd/restaurant/storage/test.jpg';
        $this->storage->save();

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute());

        $response->assertOk();
        $this->assertNull(Storage::where('uuid', $this->storage->uuid)->first());
        $this->assertFalse(StorageIngredient::where('storage_uuid', $this->storage->uuid)->exists());
        $released = $response->json('data.released');
        $this->assertCount(1, $released);
        $this->assertEquals(['type' => 'ingredient', 'uuid' => $this->ingredient->uuid, 'amount' => 3, 'days_left' => $this->ingredient->expires_in],
            array_intersect_key($released[0], array_flip(['type', 'uuid', 'amount', 'days_left'])));
        $this->assertEquals([], $response->json('data.inventory.storages'));
        FileStorage::assertMissing('modules/dnd/restaurant/storage/test.jpg');
    }

    public function test_storage_of_another_restaurant_is_not_found(): void
    {
        $otherStorage = $this->createTestStorage($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($otherStorage));

        $response->assertNotFound();
        $this->assertApiMessage('Storage not found', $response);
        $this->assertNotNull($otherStorage->fresh());
    }
}
