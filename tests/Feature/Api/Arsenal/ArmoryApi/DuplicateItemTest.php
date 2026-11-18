<?php

namespace Tests\Feature\Api\Arsenal\ArmoryApi;

use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class DuplicateItemTest extends AbstractApiFeatureTester implements PostOperationInterface
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
        return route('api.arsenal.armory.item.duplicate');
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

    //| item not found test

    public function test_duplicate_item_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'uuid' => $this->getFalseUuid(UserWarframe::class),
                'type' => 'warframe',
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Item not found', $response);
    }

    //| duplicate warframe also duplicates exalted weapons test

    public function test_duplicate_warframe_also_duplicates_exalted_weapons(): void
    {
        $user     = $this->getAdminUser();
        $warframe = $this->createTestWarframe();
        $exalted  = $this->createTestWeapon(exaltedId: $warframe->id);
        $uw       = $this->createUserWarframe($user->uuid, $warframe->id);
        $this->createUserWeapon($user->uuid, $exalted->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['uuid' => $uw->uuid, 'type' => 'warframe']);

        $response->assertCreated();
        $this->assertApiMessage('Duplicate added', $response);

        $copies = UserWeapon::where('id', $exalted->id)->where('owner_uuid', $user->uuid)->count();
        $this->assertEquals(2, $copies);
    }

    //| duplicate weapon test

    public function test_duplicate_weapon(): void
    {
        $user       = $this->getAdminUser();
        $weapon     = $this->createTestWeapon();
        $userWeapon = $this->createUserWeapon($user->uuid, $weapon->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['uuid' => $userWeapon->uuid, 'type' => 'weapon']);

        $response->assertCreated();
        $this->assertEquals(
            2,
            UserWeapon::where('id', $weapon->id)->where('owner_uuid', $user->uuid)->count()
        );
    }

    //| duplicate companion test

    public function test_duplicate_companion(): void
    {
        $user          = $this->getAdminUser();
        $companion     = $this->createTestCompanion();
        $userCompanion = $this->createUserCompanion($user->uuid, $companion->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['uuid' => $userCompanion->uuid, 'type' => 'companion']);

        $response->assertCreated();
        $this->assertEquals(
            2,
            UserCompanion::where('id', $companion->id)->where('owner_uuid', $user->uuid)->count()
        );
    }
}
