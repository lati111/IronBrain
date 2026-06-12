<?php

namespace Tests\Feature\Api\Arsenal\LoadoutApi;

use App\Models\Arsenal\Loadout;
use App\Models\Arsenal\UserWarframe;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class RenameLoadoutTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, ArsenalTestHelper;

    private Loadout $loadout;

    public function setUp(): void
    {
        parent::setUp();
        $user          = $this->getAdminUser();
        $warframe      = $this->createTestWarframe();
        $userWarframe  = $this->createUserWarframe($user->uuid, $warframe->id);
        $this->loadout = $this->createTestLoadout($user->uuid, $userWarframe->uuid);
    }

    protected function getRoute(): string
    {
        return route('api.arsenal.loadout.rename');
    }

    protected function getModel(): Loadout
    {
        return new Loadout();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'loadout_uuid' => $this->loadout->uuid,
            'name'         => 'Steel Path',
        ], $overwrites);
    }

    //| validation tests

    public function test_loadout_uuid_validation(): void
    {
        $this->assertRequiredValidation('loadout_uuid', 'loadout uuid');
        $this->assertStringValidation('loadout_uuid', 'loadout uuid');
    }

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertStringValidation('name');
        $this->assertMaxValidation('string', 'name', 32);
    }

    //| loadout not found test

    public function test_loadout_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->getFalseUuid(Loadout::class),
                'name'         => 'New Name',
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Loadout not found', $response);
    }

    //| rename success test

    public function test_rename_success(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->loadout->uuid,
                'name'         => 'Steel Path',
            ]);

        $response->assertOk();
        $this->assertApiMessage('Loadout renamed', $response);
        $this->assertEquals('Steel Path', Loadout::where('uuid', $this->loadout->uuid)->first()->name);
    }
}
