<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers editing a storage space: its name, capacity, expiration multiplier and background image. The test storage
 * space holds 100
 */
class EditStorageTest extends AbstractApiFeatureTester
{
    use RestaurantTestHelper;

    private const string IMAGE = 'modules/dnd/restaurant/storage/old.jpg';

    private Restaurant $restaurant;
    private Storage $storage;

    public function setUp(): void
    {
        parent::setUp();
        FileStorage::fake();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->storage    = $this->createTestStorage($this->restaurant, 100, 0, 'Cellar');
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(?Storage $storage = null): string
    {
        return route('api.dnd.restaurant.inventory.storage.edit', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'storage_uuid'    => ($storage ?? $this->storage)->uuid,
        ]);
    }

    protected function getModel(): Storage
    {
        return new Storage();
    }

    private function edit(array $parameters, ?Storage $storage = null): \Illuminate\Testing\TestResponse
    {
        return $this->getHttpClient($this->getOperationUser())->post($this->getRoute($storage), $parameters);
    }

    /** Gives the test storage space a background image */
    private function addImage(): void
    {
        FileStorage::put(self::IMAGE, 'image');
        $this->storage->background_image = self::IMAGE;
        $this->storage->save();
    }

    public function test_storage_is_edited(): void
    {
        $response = $this->edit(['name' => 'Pantry', 'capacity' => 250, 'expiration_multiplier' => 0.5]);

        $response->assertOk();
        $this->assertApiMessage('Storage space updated', $response);
        $this->storage->refresh();
        $this->assertEquals('Pantry', $this->storage->name);
        $this->assertEquals(250, $this->storage->capacity);
        $this->assertEquals(0.5, $this->storage->expiration_multiplier);
        $this->assertEquals('Pantry', $response->json('data.inventory.storages.0.name'));
    }

    public function test_fields_left_out_keep_their_value(): void
    {
        $this->edit(['name' => 'Pantry'])->assertOk();

        $this->storage->refresh();
        $this->assertEquals('Pantry', $this->storage->name);
        $this->assertEquals(100, $this->storage->capacity);
    }

    public function test_capacity_cannot_go_below_what_is_stored(): void
    {
        $ingredient = $this->createTestIngredient($this->restaurant, 2, 'Flour');
        $this->storeIngredient($this->storage, $ingredient, 30);

        $response = $this->edit(['capacity' => 59]);

        $response->assertBadRequest();
        $this->assertApiMessage('Cellar holds 60, so its capacity can\'t be lower than that', $response);
        $this->assertEquals(100, $this->storage->fresh()->capacity);

        // Shrinking down to exactly what it holds is fine
        $this->edit(['capacity' => 60])->assertOk();
        $this->assertEquals(60, $this->storage->fresh()->capacity);
    }

    public function test_background_image_is_replaced(): void
    {
        $this->addImage();

        $this->edit(['background_image' => UploadedFile::fake()->image('pantry.jpg')])->assertOk();

        $this->storage->refresh();
        $this->assertNotEquals(self::IMAGE, $this->storage->background_image);
        FileStorage::assertExists($this->storage->background_image);
        FileStorage::assertMissing(self::IMAGE);
    }

    public function test_background_image_is_removed(): void
    {
        $this->addImage();

        $this->edit(['remove_background_image' => 1])->assertOk();

        $this->assertNull($this->storage->fresh()->background_image);
        FileStorage::assertMissing(self::IMAGE);
    }

    public function test_background_image_is_kept_when_not_changed(): void
    {
        $this->addImage();

        $this->edit(['name' => 'Pantry'])->assertOk();

        $this->assertEquals(self::IMAGE, $this->storage->fresh()->background_image);
        FileStorage::assertExists(self::IMAGE);
    }

    public function test_validation(): void
    {
        $this->edit(['capacity' => 0])->assertBadRequest();
        $this->edit(['expiration_multiplier' => -1])->assertBadRequest();
        $this->edit(['name' => str_repeat('a', 65)])->assertBadRequest();
        $this->assertEquals('Cellar', $this->storage->fresh()->name);
    }

    public function test_storage_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestStorage($this->createTestRestaurant($this->getAdminUser(), 'Other'), 100, 0, 'Other');

        $response = $this->edit(['name' => 'Mine now'], $other);

        $response->assertNotFound();
        $this->assertApiMessage('Storage not found', $response);
        $this->assertEquals('Other', $other->fresh()->name);
    }
}
