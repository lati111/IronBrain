<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class AdjustOwnedEquipmentTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;
    private Equipment $equipment;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->equipment  = $this->createTestEquipment($this->restaurant, 1, 'Ladle', false);
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.inventory.equipment.owned', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'equipment_uuid'  => $this->equipment->uuid,
        ]);
    }

    protected function getModel(): Equipment
    {
        return new Equipment();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'amount' => 2,
        ], $overwrites);
    }

    //| validation tests

    public function test_amount_validation(): void
    {
        $this->assertRequiredValidation('amount');
        $this->assertIntegerValidation('amount');
    }

    //| save tests

    public function test_adding_increases_the_owned_amount(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters());

        $response->assertOk();
        $this->assertEquals(2, $this->getOwnedEquipmentAmount($this->restaurant, $this->equipment));
        $this->assertEquals(2, $response->json('data.inventory.equipment.0.stored'));
    }

    public function test_removing_decreases_the_owned_amount(): void
    {
        $this->ownEquipment($this->restaurant, $this->equipment, 5);

        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['amount' => -2]))
            ->assertOk();

        $this->assertEquals(3, $this->getOwnedEquipmentAmount($this->restaurant, $this->equipment));
    }

    public function test_removing_more_than_owned_fails(): void
    {
        $this->ownEquipment($this->restaurant, $this->equipment, 1);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['amount' => -2]));

        $response->assertBadRequest();
        $this->assertApiMessage('Not enough owned to remove', $response);
        $this->assertEquals(1, $this->getOwnedEquipmentAmount($this->restaurant, $this->equipment));
    }

    public function test_amount_cannot_be_zero(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['amount' => 0]));

        $response->assertBadRequest();
        $this->assertApiMessage('Amount cannot be zero', $response);
    }

    public function test_equipment_that_requires_storage_cannot_be_adjusted(): void
    {
        $equipment = $this->createTestEquipment($this->restaurant, 1, 'Pot', true);

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.inventory.equipment.owned', [
                'restaurant_uuid' => $this->restaurant->uuid,
                'equipment_uuid'  => $equipment->uuid,
            ]),
            $this->getPostParameters(),
        );

        $response->assertBadRequest();
        $this->assertApiMessage('This equipment must be stored in a storage space', $response);
    }

    public function test_equipment_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestRestaurant($this->getAdminUser(), 'Other');
        $otherEquipment = $this->createTestEquipment($other, 1, 'Ladle', false);

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.inventory.equipment.owned', [
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
            route('api.dnd.restaurant.inventory.equipment.owned', [
                'restaurant_uuid' => $restaurant->uuid,
                'equipment_uuid'  => $this->equipment->uuid,
            ]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
