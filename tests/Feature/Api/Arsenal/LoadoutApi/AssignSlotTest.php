<?php

namespace Tests\Feature\Api\Arsenal\LoadoutApi;

use App\Models\Arsenal\Loadout;
use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class AssignSlotTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, ArsenalTestHelper;

    private Loadout      $loadout;
    private UserWarframe $userWarframe;
    private UserWeapon   $primaryWeapon;

    public function setUp(): void
    {
        parent::setUp();
        $user                = $this->getAdminUser();
        $warframe            = $this->createTestWarframe();
        $this->userWarframe  = $this->createUserWarframe($user->uuid, $warframe->id);
        $this->loadout       = $this->createTestLoadout($user->uuid, $this->userWarframe->uuid);
        $primaryBase         = $this->createTestWeapon(type: 'primary');
        $this->primaryWeapon = $this->createUserWeapon($user->uuid, $primaryBase->id);
    }

    protected function getRoute(): string
    {
        return route('api.arsenal.loadout.slot.assign');
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
            'item_uuid'    => $this->primaryWeapon->uuid,
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

    public function test_item_uuid_validation(): void
    {
        $this->assertRequiredValidation('item_uuid', 'item uuid');
        $this->assertStringValidation('item_uuid', 'item uuid');
    }

    //| loadout not found test

    public function test_loadout_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->getFalseUuid(Loadout::class),
                'slot'         => 'primary',
                'item_uuid'    => $this->primaryWeapon->uuid,
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Loadout not found', $response);
    }

    //| item not found tests (covers fetchSlotItem not-found branches)

    public function test_assign_warframe_slot_item_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->loadout->uuid,
                'slot'         => 'warframe',
                'item_uuid'    => $this->getFalseUuid(UserWarframe::class),
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Warframe not found in collection', $response);
    }

    public function test_assign_weapon_slot_item_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->loadout->uuid,
                'slot'         => 'primary',
                'item_uuid'    => $this->getFalseUuid(UserWeapon::class),
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Weapon not found in collection', $response);
    }

    public function test_assign_companion_slot_item_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->loadout->uuid,
                'slot'         => 'companion',
                'item_uuid'    => $this->getFalseUuid(UserCompanion::class),
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Companion not found in collection', $response);
    }

    //| assign slot success tests (covers all match branches)

    public function test_assign_warframe_slot(): void
    {
        $user     = $this->getAdminUser();
        $warframe = $this->createTestWarframe();
        $uw       = $this->createUserWarframe($user->uuid, $warframe->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->loadout->uuid,
                'slot'         => 'warframe',
                'item_uuid'    => $uw->uuid,
            ]);

        $response->assertOk();
        $this->assertEquals($uw->uuid, Loadout::where('uuid', $this->loadout->uuid)->first()->warframe_uuid);
    }

    public function test_assign_companion_slot(): void
    {
        $user          = $this->getAdminUser();
        $companion     = $this->createTestCompanion();
        $userCompanion = $this->createUserCompanion($user->uuid, $companion->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->loadout->uuid,
                'slot'         => 'companion',
                'item_uuid'    => $userCompanion->uuid,
            ]);

        $response->assertOk();
        $this->assertEquals(
            $userCompanion->uuid,
            Loadout::where('uuid', $this->loadout->uuid)->first()->companion_uuid
        );
    }

    public function test_assign_primary_slot(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->loadout->uuid,
                'slot'         => 'primary',
                'item_uuid'    => $this->primaryWeapon->uuid,
            ]);

        $response->assertOk();
        $this->assertEquals(
            $this->primaryWeapon->uuid,
            Loadout::where('uuid', $this->loadout->uuid)->first()->primary_uuid
        );
    }

    public function test_assign_companion_weapon_slot(): void
    {
        $user              = $this->getAdminUser();
        $companionWeapon   = $this->createTestWeapon(type: 'companion_weapon');
        $userCompWeapon    = $this->createUserWeapon($user->uuid, $companionWeapon->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'loadout_uuid' => $this->loadout->uuid,
                'slot'         => 'companion_weapon',
                'item_uuid'    => $userCompWeapon->uuid,
            ]);

        $response->assertOk();
        $this->assertEquals(
            $userCompWeapon->uuid,
            Loadout::where('uuid', $this->loadout->uuid)->first()->companion_weapon_uuid
        );
    }
}
