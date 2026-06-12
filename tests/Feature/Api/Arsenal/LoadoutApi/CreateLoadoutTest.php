<?php

namespace Tests\Feature\Api\Arsenal\LoadoutApi;

use App\Models\Arsenal\Loadout;
use App\Models\Arsenal\UserWarframe;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateLoadoutTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, ArsenalTestHelper;

    private UserWarframe $userWarframe;

    public function setUp(): void
    {
        parent::setUp();
        $user               = $this->getAdminUser();
        $warframe           = $this->createTestWarframe();
        $this->userWarframe = $this->createUserWarframe($user->uuid, $warframe->id);
    }

    protected function getRoute(): string
    {
        return route('api.arsenal.loadout.create');
    }

    protected function getModel(): Loadout
    {
        return new Loadout();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'warframe_uuid' => $this->userWarframe->uuid,
        ], $overwrites);
    }

    //| validation tests

    public function test_warframe_uuid_validation(): void
    {
        $this->assertRequiredValidation('warframe_uuid', 'warframe uuid');
        $this->assertStringValidation('warframe_uuid', 'warframe uuid');
    }

    //| warframe not in collection test

    public function test_warframe_not_found_in_collection(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['warframe_uuid' => $this->getFalseUuid(UserWarframe::class)]);

        $response->assertNotFound();
        $this->assertApiMessage('Warframe not found in collection', $response);
    }

    //| loadout name auto-increments test

    public function test_loadout_name_increments(): void
    {
        $user = $this->getAdminUser();

        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['warframe_uuid' => $this->userWarframe->uuid]);

        $warframe2  = $this->createTestWarframe();
        $uw2        = $this->createUserWarframe($user->uuid, $warframe2->id);
        $response   = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['warframe_uuid' => $uw2->uuid]);

        $response->assertCreated();
        $uuid    = $response->json()['data'];
        $loadout = Loadout::where('uuid', $uuid)->first();
        $this->assertEquals('Loadout 2', $loadout->name);
    }
}
