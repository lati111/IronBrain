<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateEquipmentTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.inventory.equipment.create', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Equipment
    {
        return new Equipment();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'          => 'Cooking Pot',
            'category'      => 'cookware',
            'cleaning_time' => 10,
            'multiplier'    => 1.25,
            'size'          => 3,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_category_validation(): void
    {
        $this->assertRequiredValidation('category');
        $this->assertInArrayValidation('category', 'spaceship');
    }

    public function test_category_is_saved_as_its_code_and_listed_with_its_label(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['category' => 'tableware']));

        $response->assertCreated();
        $equipment = Equipment::where('uuid', $response->json('data.uuid'))->first();
        $this->assertSame(EquipmentCategory::TABLEWARE, $equipment->category);
        $this->assertEquals('tableware', $response->json('data.inventory.equipment.0.category'));
        $this->assertEquals('Tableware', $response->json('data.inventory.equipment.0.category_label'));
    }

    public function test_numeric_field_validation(): void
    {
        $this->assertNumericValidation('cleaning_time', 'cleaning time');
        $this->assertNumericValidation('multiplier');
        $this->assertNumericValidation('size');
    }

    //| save tests

    public function test_size_and_cleaning_time_can_be_fractions(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['size' => 0.5, 'cleaning_time' => 2.5]));

        $response->assertCreated();
        $equipment = Equipment::where('restaurant_uuid', $this->restaurant->uuid)->latest()->first();
        $this->assertEquals(0.5, $equipment->size);
        $this->assertEquals(2.5, $equipment->cleaning_time);
    }

    public function test_size_must_be_above_zero(): void
    {
        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['size' => 0]))
            ->assertBadRequest();
    }

    public function test_equipment_is_saved_with_parent(): void
    {
        $parent = $this->createTestEquipment($this->restaurant, 5, 'Stove');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['parent_uuid' => $parent->uuid]));

        $response->assertCreated();
        $equipment = Equipment::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($parent->uuid, $equipment->parent_uuid);
        $this->assertEquals(3, $equipment->size);
        $this->assertEquals(1.25, $equipment->multiplier);
    }

    public function test_parent_of_another_restaurant_is_not_found(): void
    {
        $otherParent = $this->createTestEquipment($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['parent_uuid' => $otherParent->uuid]));

        $response->assertNotFound();
        $this->assertApiMessage('Parent equipment not found', $response);
    }

    public function test_requires_storage_defaults_to_true(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters());

        $response->assertCreated();
        $equipment = Equipment::where('uuid', $response->json('data.uuid'))->first();
        $this->assertTrue($equipment->requires_storage);
    }

    public function test_requires_storage_can_be_disabled(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['requires_storage' => false]));

        $response->assertCreated();
        $equipment = Equipment::where('uuid', $response->json('data.uuid'))->first();
        $this->assertFalse($equipment->requires_storage);
    }
}
