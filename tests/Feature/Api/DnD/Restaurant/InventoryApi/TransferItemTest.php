<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageEquipment;
use App\Models\DnD\Restaurant\StorageIngredient;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class TransferItemTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;
    private Storage $storage;
    private Ingredient $ingredient;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->storage    = $this->createTestStorage($this->restaurant, 10);
        $this->ingredient = $this->createTestIngredient($this->restaurant, 2);
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.inventory.transfer', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): StorageIngredient
    {
        return new StorageIngredient();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'type'            => 'ingredient',
            'item_uuid'       => $this->ingredient->uuid,
            'amount'          => 3,
            'to_storage_uuid' => $this->storage->uuid,
        ], $overwrites);
    }

    private function transfer(array $overwrites = [])
    {
        return $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters($overwrites));
    }

    //| validation tests

    public function test_type_validation(): void
    {
        $this->assertRequiredValidation('type');
        $this->assertInArrayValidation('type', 'spell');
    }

    public function test_item_uuid_validation(): void
    {
        $this->assertRequiredValidation('item_uuid', 'item uuid');
        $this->assertStringValidation('item_uuid', 'item uuid');
    }

    public function test_amount_validation(): void
    {
        $this->assertRequiredValidation('amount');
        $this->assertIntegerValidation('amount');
    }

    public function test_requires_a_storage(): void
    {
        $response = $this->transfer(['to_storage_uuid' => null]);

        $response->assertBadRequest();
        $this->assertApiMessage('A storage to move from or to is required', $response);
    }

    //| transfer tests

    public function test_general_to_storage_saves_the_item(): void
    {
        $response = $this->transfer();

        $response->assertOk();
        $this->assertEquals(3, $this->getStoredIngredientAmount($this->storage, $this->ingredient));
        $this->assertEquals(6, $response->json('data.inventory.storages.0.used_capacity'));
    }

    public function test_adding_to_an_existing_stack_increments_it(): void
    {
        $this->storeIngredient($this->storage, $this->ingredient, 1);

        $this->transfer(['amount' => 2])->assertOk();

        $this->assertEquals(3, $this->getStoredIngredientAmount($this->storage, $this->ingredient));
        $this->assertEquals(1, StorageIngredient::where('storage_uuid', $this->storage->uuid)->count());
    }

    public function test_storage_to_storage_moves_part_of_a_stack(): void
    {
        $target = $this->createTestStorage($this->restaurant, 10, 1);
        $this->storeIngredient($this->storage, $this->ingredient, 4);

        $this->transfer([
            'amount'            => 1,
            'from_storage_uuid' => $this->storage->uuid,
            'to_storage_uuid'   => $target->uuid,
        ])->assertOk();

        $this->assertEquals(3, $this->getStoredIngredientAmount($this->storage, $this->ingredient));
        $this->assertEquals(1, $this->getStoredIngredientAmount($target, $this->ingredient));
    }

    public function test_storage_to_general_removes_the_stack_when_emptied(): void
    {
        $this->storeIngredient($this->storage, $this->ingredient, 2);

        $this->transfer([
            'amount'            => 2,
            'from_storage_uuid' => $this->storage->uuid,
            'to_storage_uuid'   => null,
        ])->assertOk();

        $this->assertFalse(StorageIngredient::where('storage_uuid', $this->storage->uuid)->exists());
    }

    public function test_equipment_can_be_stored_and_uses_capacity(): void
    {
        $equipment = $this->createTestEquipment($this->restaurant, 4);

        $response = $this->transfer(['type' => 'equipment', 'item_uuid' => $equipment->uuid, 'amount' => 2]);

        $response->assertOk();
        $this->assertEquals(2, StorageEquipment::where('storage_uuid', $this->storage->uuid)->value('amount'));
        $this->assertEquals(8, $response->json('data.inventory.storages.0.used_capacity'));
    }

    public function test_fractional_sizes_can_fill_a_storage_exactly(): void
    {
        $storage = $this->createTestStorage($this->restaurant, 1, 1, 'Jar');
        $spice = $this->createTestIngredient($this->restaurant, 1, 'Spice');
        $spice->size = 0.1;
        $spice->save();
        $this->storeIngredient($storage, $spice, 7);

        // Without rounding, 0.7 + 3 * 0.1 comes out just above 1 in floating point
        $response = $this->transfer(['item_uuid' => $spice->uuid, 'to_storage_uuid' => $storage->uuid]);

        $response->assertOk();
        $this->assertEquals(10, $this->getStoredIngredientAmount($storage, $spice));
        $this->assertEquals(1, $response->json('data.inventory.storages.1.used_capacity'));
    }

    public function test_equipment_that_does_not_require_storage_cannot_be_transferred(): void
    {
        $equipment = $this->createTestEquipment($this->restaurant, 1, 'Ladle', false);

        $response = $this->transfer(['type' => 'equipment', 'item_uuid' => $equipment->uuid]);

        $response->assertBadRequest();
        $this->assertApiMessage('This equipment does not require storage', $response);
    }

    //| failure tests

    public function test_capacity_cannot_be_exceeded(): void
    {
        $response = $this->transfer(['amount' => 6]);

        $response->assertBadRequest();
        $this->assertApiMessage('Not enough capacity in Test Storage', $response);
        $this->assertFalse(StorageIngredient::where('storage_uuid', $this->storage->uuid)->exists());
    }

    public function test_cannot_move_more_than_stored(): void
    {
        $target = $this->createTestStorage($this->restaurant, 10, 1);
        $this->storeIngredient($this->storage, $this->ingredient, 1);

        $response = $this->transfer([
            'amount'            => 2,
            'from_storage_uuid' => $this->storage->uuid,
            'to_storage_uuid'   => $target->uuid,
        ]);

        $response->assertBadRequest();
        $this->assertApiMessage('Not enough stored to move', $response);
        $this->assertEquals(1, $this->getStoredIngredientAmount($this->storage, $this->ingredient));
    }

    public function test_failed_capacity_check_keeps_the_source_intact(): void
    {
        $target = $this->createTestStorage($this->restaurant, 1, 1);
        $this->storeIngredient($this->storage, $this->ingredient, 2);

        $this->transfer([
            'amount'            => 1,
            'from_storage_uuid' => $this->storage->uuid,
            'to_storage_uuid'   => $target->uuid,
        ])->assertBadRequest();

        $this->assertEquals(2, $this->getStoredIngredientAmount($this->storage, $this->ingredient));
    }

    public function test_storage_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestRestaurant($this->getAdminUser(), 'Other');
        $otherStorage = $this->createTestStorage($other);

        $response = $this->transfer(['to_storage_uuid' => $otherStorage->uuid]);

        $response->assertNotFound();
        $this->assertApiMessage('Storage not found', $response);
    }

    public function test_item_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestRestaurant($this->getAdminUser(), 'Other');
        $otherIngredient = $this->createTestIngredient($other);

        $response = $this->transfer(['item_uuid' => $otherIngredient->uuid]);

        $response->assertNotFound();
        $this->assertApiMessage('Item not found', $response);
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post(route('api.dnd.restaurant.inventory.transfer', ['restaurant_uuid' => $restaurant->uuid]), $this->getPostParameters());

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
