<?php

namespace Tests\Feature\Api\Arsenal\ArmoryApi;

use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class RemoveItemTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, ArsenalTestHelper;

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
        return route('api.arsenal.armory.item.remove');
    }

    protected function getModel(): UserWarframe
    {
        return new UserWarframe();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'uuid' => $this->userWarframe->uuid,
            'type' => 'warframe',
        ], $overwrites);
    }

    //| validation tests

    public function test_uuid_validation(): void
    {
        $this->assertRequiredValidation('uuid');
        $this->assertStringValidation('uuid');
        $this->assertMaxValidation('string', 'uuid', 255);
    }

    public function test_type_validation(): void
    {
        $this->assertRequiredValidation('type');
        $this->assertStringValidation('type');
        $this->assertInArrayValidation('type', 'invalid_type');
    }

    //| item not found tests

    public function test_remove_item_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'uuid' => $this->getFalseUuid(UserWarframe::class),
                'type' => 'warframe',
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Item not found', $response);
    }

    //| remove warframe also removes exalted weapons test

    public function test_remove_warframe_also_removes_exalted_weapons(): void
    {
        $user       = $this->getAdminUser();
        $warframe   = $this->createTestWarframe();
        $exalted    = $this->createTestWeapon(exaltedId: $warframe->id);
        $uw         = $this->createUserWarframe($user->uuid, $warframe->id);
        $uExalted   = $this->createUserWeapon($user->uuid, $exalted->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['uuid' => $uw->uuid, 'type' => 'warframe']);

        $response->assertOk();
        $this->assertNull(UserWarframe::where('uuid', $uw->uuid)->first());
        $this->assertNull(UserWeapon::where('uuid', $uExalted->uuid)->first());
    }

    //| remove warframe without exalted weapons test

    public function test_remove_warframe_success(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['uuid' => $this->userWarframe->uuid, 'type' => 'warframe']);

        $response->assertOk();
        $this->assertApiMessage('Removed from collection', $response);
        $this->assertNull(UserWarframe::where('uuid', $this->userWarframe->uuid)->first());
    }

    //| remove weapon test

    public function test_remove_weapon_success(): void
    {
        $user       = $this->getAdminUser();
        $weapon     = $this->createTestWeapon();
        $userWeapon = $this->createUserWeapon($user->uuid, $weapon->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['uuid' => $userWeapon->uuid, 'type' => 'weapon']);

        $response->assertOk();
        $this->assertNull(UserWeapon::where('uuid', $userWeapon->uuid)->first());
    }

    //| remove companion test

    public function test_remove_companion_success(): void
    {
        $user          = $this->getAdminUser();
        $companion     = $this->createTestCompanion();
        $userCompanion = $this->createUserCompanion($user->uuid, $companion->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['uuid' => $userCompanion->uuid, 'type' => 'companion']);

        $response->assertOk();
        $this->assertNull(UserCompanion::where('uuid', $userCompanion->uuid)->first());
    }
}
