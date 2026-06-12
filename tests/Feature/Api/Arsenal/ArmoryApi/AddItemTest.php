<?php

namespace Tests\Feature\Api\Arsenal\ArmoryApi;

use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use App\Models\Auth\User;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class AddItemTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, ArsenalTestHelper;

    private Warframe $warframe;

    public function setUp(): void
    {
        parent::setUp();
        $this->warframe = $this->createTestWarframe();
    }

    protected function getRoute(): string
    {
        return route('api.arsenal.armory.add');
    }

    protected function getModel(): UserWarframe
    {
        return new UserWarframe();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'id'   => $this->warframe->id,
            'type' => 'warframe',
        ], $overwrites);
    }

    //| validation tests

    public function test_id_validation(): void
    {
        $this->assertRequiredValidation('id');
        $this->assertStringValidation('id');
        $this->assertMaxValidation('string', 'id', 255);
    }

    public function test_type_validation(): void
    {
        $this->assertRequiredValidation('type');
        $this->assertStringValidation('type');
        $this->assertInArrayValidation('type', 'invalid_type');
    }

    //| item not found test

    public function test_add_item_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['id' => 'nonexistent_item', 'type' => 'warframe']);

        $response->assertNotFound();
        $this->assertApiMessage('Warframe not found', $response);
    }

    //| exalted weapon forbidden test

    public function test_add_exalted_weapon_forbidden(): void
    {
        $exaltedWeapon = $this->createTestWeapon(exaltedId: $this->warframe->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['id' => $exaltedWeapon->id, 'type' => 'weapon']);

        $response->assertForbidden();
        $this->assertApiMessage('Exalted weapons cannot be added manually', $response);
    }

    //| already in collection test

    public function test_add_item_already_in_collection(): void
    {
        $user = $this->getAdminUser();
        $this->createUserWarframe($user->uuid, $this->warframe->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['id' => $this->warframe->id, 'type' => 'warframe']);

        $response->assertStatus(208);
        $this->assertApiMessage('Already in collection', $response);
    }

    //| add warframe with exalted weapons test

    public function test_add_warframe_also_adds_exalted_weapons(): void
    {
        $exaltedWeapon = $this->createTestWeapon(exaltedId: $this->warframe->id);
        $user = $this->getAdminUser();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['id' => $this->warframe->id, 'type' => 'warframe']);

        $response->assertCreated();
        $this->assertNotNull(
            UserWeapon::where('id', $exaltedWeapon->id)->where('owner_uuid', $user->uuid)->first()
        );
    }

    //| add weapon test

    public function test_add_weapon_success(): void
    {
        $weapon = $this->createTestWeapon();
        $user   = $this->getAdminUser();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['id' => $weapon->id, 'type' => 'weapon']);

        $response->assertCreated();
        $this->assertNotNull(
            UserWeapon::where('id', $weapon->id)->where('owner_uuid', $user->uuid)->first()
        );
    }

    //| add companion test

    public function test_add_companion_success(): void
    {
        $companion = $this->createTestCompanion();
        $user      = $this->getAdminUser();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['id' => $companion->id, 'type' => 'companion']);

        $response->assertCreated();
        $this->assertNotNull(
            \App\Models\Arsenal\UserCompanion::where('id', $companion->id)->where('owner_uuid', $user->uuid)->first()
        );
    }
}
