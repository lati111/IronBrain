<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateStorageTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        FileStorage::fake();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.inventory.storage.create', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Storage
    {
        return new Storage();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'                  => 'Cellar',
            'capacity'              => 100,
            'expiration_multiplier' => 1.5,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertStringValidation('name');
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_capacity_validation(): void
    {
        $this->assertRequiredValidation('capacity');
        $this->assertIntegerValidation('capacity');
    }

    public function test_expiration_multiplier_validation(): void
    {
        $this->assertRequiredValidation('expiration_multiplier', 'expiration multiplier');
        $this->assertNumericValidation('expiration_multiplier', 'expiration multiplier');
    }

    //| save tests

    public function test_storage_is_added_at_the_end(): void
    {
        $this->createTestStorage($this->restaurant, 10, 4);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters());

        $response->assertCreated();
        $storage = Storage::where('restaurant_uuid', $this->restaurant->uuid)->where('name', 'Cellar')->first();
        $this->assertEquals(5, $storage->order);
        $this->assertEquals(1.5, $storage->expiration_multiplier);
        $this->assertNull($storage->background_image);
        $this->assertEquals('Cellar', $response->json('data.inventory.storages.1.name'));
    }

    public function test_background_image_is_uploaded(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'background_image' => UploadedFile::fake()->image('cellar.jpg'),
            ]));

        $response->assertCreated();
        $storage = Storage::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $this->assertStringStartsWith('modules/dnd/restaurant/storage/', $storage->background_image);
        FileStorage::assertExists($storage->background_image);
    }
}
