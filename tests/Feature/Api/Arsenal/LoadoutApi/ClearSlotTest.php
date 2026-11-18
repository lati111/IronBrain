<?php

namespace Tests\Feature\Api\Arsenal\LoadoutApi;

use App\Models\Arsenal\Loadout;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class ClearSlotTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, ArsenalTestHelper;

    private Loadout      $loadout;
    private UserWeapon   $primaryWeapon;

    public function setUp(): void
    {
        parent::setUp();
        $user                = $this->getAdminUser();
        $warframe            = $this->createTestWarframe();
        $userWarframe        = $this->createUserWarframe($user->uuid, $warframe->id);
        $primaryBase         = $this->createTestWeapon(type: 'primary');
        $this->primaryWeapon = $this->createUserWeapon($user->uuid, $primaryBase->id);

        $this->loadout                = $this->createTestLoadout($user->uuid, $userWarframe->uuid);
        $this->loadout->primary_uuid  = $this->primaryWeapon->uuid;
        $this->loadout->save();
    }

    protected function getRoute(): string
    {
        return route('api.arsenal.loadout.slot.clear');
    }

    protected function getModel(): Loadout
    {
        return new Loadout();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'loadout_uuid' => $this->loadout->uuid,
            'slot'         => 'primary',
        ], $overwrites);
    }

    //| validation tests

    public function test_loadout_uuid_validation(): void
    {
        $this->assertRequiredValidation('loadout_uuid', 'loadout uuid');
        $this->assertStringValidation('loadout_uuid', 'loadout uuid');
    }

    public function test_slot_validation(): void
    {
        $this->assertRequiredValidation('slot');
        $this->assertStringValidation('slot');
        $this->assertInArrayValidation('slot', 'invalid_slot');
    }

    //| warframe slot cannot be cleared test

    public function test_warframe_slot_cannot_be_cleared(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->loadout->uuid,
                'slot'         => 'warframe',
            ]);

        $response->assertBadRequest();
        $this->assertApiMessage('Warframe slot cannot be cleared', $response);
    }

    //| loadout not found test

    public function test_loadout_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->getFalseUuid(Loadout::class),
                'slot'         => 'primary',
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Loadout not found', $response);
    }

    //| clear slot success test

    public function test_clear_slot_success(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->loadout->uuid,
                'slot'         => 'primary',
            ]);

        $response->assertOk();
        $this->assertApiMessage('Slot cleared', $response);
        $this->assertNull(Loadout::where('uuid', $this->loadout->uuid)->first()->primary_uuid);
    }
}
