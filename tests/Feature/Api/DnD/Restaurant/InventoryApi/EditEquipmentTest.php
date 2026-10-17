<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostEditOperation;
use Tests\Traits\ValidationTests;

class EditEquipmentTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostEditOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->item = $this->createTestEquipment($this->restaurant, 3, 'Cooking Pot');
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.inventory.equipment.edit', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'equipment_uuid'  => $this->item->uuid,
        ]);
    }

    protected function getModel(): Equipment
    {
        return new Equipment();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'          => 'Big Cooking Pot',
            'category'      => 'cooker',
            'cleaning_time' => 15,
            'multiplier'    => 1.5,
            'size'          => 4,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_category_validation(): void
    {
        $this->assertInArrayValidation('category', 'spaceship');
    }

    public function test_numeric_field_validation(): void
    {
        $this->assertNumericValidation('cleaning_time', 'cleaning time');
        $this->assertNumericValidation('multiplier');
        $this->assertNumericValidation('size');
    }

    //| save tests

    public function test_equipment_can_be_given_a_parent(): void
    {
        $parent = $this->createTestEquipment($this->restaurant, 5, 'Stove');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['parent_uuid' => $parent->uuid]));

        $response->assertOk();
        $this->assertEquals($parent->uuid, Equipment::where('uuid', $this->item->uuid)->value('parent_uuid'));
    }

    public function test_equipment_cannot_be_its_own_parent(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['parent_uuid' => $this->item->uuid]));

        $response->assertBadRequest();
        $this->assertApiMessage('Equipment cannot be its own parent', $response);
    }

    public function test_parent_of_another_restaurant_is_not_found(): void
    {
        $otherParent = $this->createTestEquipment($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['parent_uuid' => $otherParent->uuid]));

        $response->assertNotFound();
        $this->assertApiMessage('Parent equipment not found', $response);
    }

    public function test_requires_storage_can_be_disabled_when_nothing_is_stored(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['requires_storage' => false]));

        $response->assertOk();
        $this->assertFalse((bool) Equipment::where('uuid', $this->item->uuid)->value('requires_storage'));
    }

    public function test_requires_storage_cannot_be_changed_while_stored_in_a_storage_space(): void
    {
        $storage = $this->createTestStorage($this->restaurant);
        $this->storeEquipment($storage, $this->item, 1);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['requires_storage' => false]));

        $response->assertBadRequest();
        $this->assertApiMessage('Remove all stored and owned Cooking Pot before changing its storage requirement', $response);
        $this->assertTrue((bool) Equipment::where('uuid', $this->item->uuid)->value('requires_storage'));
    }

    public function test_requires_storage_cannot_be_changed_while_owned(): void
    {
        $this->item->requires_storage = false;
        $this->item->save();
        $this->ownEquipment($this->restaurant, $this->item, 2);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['requires_storage' => true]));

        $response->assertBadRequest();
        $this->assertFalse((bool) Equipment::where('uuid', $this->item->uuid)->value('requires_storage'));
    }

    public function test_equipment_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestRestaurant($this->getAdminUser(), 'Other');
        $otherEquipment = $this->createTestEquipment($other);

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.inventory.equipment.edit', [
                'restaurant_uuid' => $this->restaurant->uuid,
                'equipment_uuid'  => $otherEquipment->uuid,
            ]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Equipment not found', $response);
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.inventory.equipment.edit', [
                'restaurant_uuid' => $restaurant->uuid,
                'equipment_uuid'  => $this->item->uuid,
            ]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
