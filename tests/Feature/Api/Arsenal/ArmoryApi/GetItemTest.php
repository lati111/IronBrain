<?php

namespace Tests\Feature\Api\Arsenal\ArmoryApi;

use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\Arsenal\ArsenalTestHelper;

class GetItemTest extends AbstractApiFeatureTester
{
    use ArsenalTestHelper;

    private UserWarframe $userWarframe;

    public function setUp(): void
    {
        parent::setUp();
        $user                = $this->getAdminUser();
        $warframe            = $this->createTestWarframe();
        $this->userWarframe  = $this->createUserWarframe($user->uuid, $warframe->id);
    }

    protected function getOperationType(): string
    {
        return 'GET';
    }

    protected function getRoute(): string
    {
        return route('api.arsenal.armory.item.get');
    }

    protected function getModel(): UserWarframe
    {
        return new UserWarframe();
    }

    //| validation tests

    public function test_uuid_validation(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->get($this->getRoute());

        $response->assertBadRequest();
        $this->assertContains('The uuid field is required.', $response->json()['errors']['uuid']);
    }

    public function test_type_validation(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->get($this->getRoute() . '?uuid=some-uuid&type=invalid');

        $response->assertBadRequest();
        $this->assertContains('The selected type is invalid.', $response->json()['errors']['type']);
    }

    //| item not found tests

    public function test_get_item_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->get(route('api.arsenal.armory.item.get', ['uuid' => $this->getFalseUuid(UserWarframe::class), 'type' => 'warframe']));

        $response->assertNotFound();
        $this->assertApiMessage('Item not found', $response);
    }

    //| get warframe test

    public function test_get_warframe(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->get(route('api.arsenal.armory.item.get', ['uuid' => $this->userWarframe->uuid, 'type' => 'warframe']));

        $response->assertOk();
        $this->assertApiMessage('Data retrieved', $response);
        $data = $response->json()['data'];
        $this->assertArrayHasKey('forma', $data);
        $this->assertArrayHasKey('shards', $data);
        $this->assertArrayHasKey('potato', $data);
        $this->assertArrayHasKey('built', $data);
        $this->assertArrayHasKey('exilus', $data);
        $this->assertArrayHasKey('fashioned', $data);
        $this->assertArrayHasKey('school', $data);
    }

    //| get weapon test

    public function test_get_weapon(): void
    {
        $user       = $this->getAdminUser();
        $weapon     = $this->createTestWeapon();
        $userWeapon = $this->createUserWeapon($user->uuid, $weapon->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->get(route('api.arsenal.armory.item.get', ['uuid' => $userWeapon->uuid, 'type' => 'weapon']));

        $response->assertOk();
        $this->assertApiMessage('Data retrieved', $response);
        $data = $response->json()['data'];
        $this->assertArrayHasKey('forma', $data);
        $this->assertArrayHasKey('riven', $data);
        $this->assertArrayHasKey('potato', $data);
        $this->assertArrayHasKey('built', $data);
        $this->assertArrayHasKey('exilus', $data);
        $this->assertArrayHasKey('school', $data);
    }

    //| get companion test

    public function test_get_companion(): void
    {
        $user          = $this->getAdminUser();
        $companion     = $this->createTestCompanion();
        $userCompanion = $this->createUserCompanion($user->uuid, $companion->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->get(route('api.arsenal.armory.item.get', ['uuid' => $userCompanion->uuid, 'type' => 'companion']));

        $response->assertOk();
        $this->assertApiMessage('Data retrieved', $response);
        $data = $response->json()['data'];
        $this->assertArrayHasKey('forma', $data);
        $this->assertArrayHasKey('potato', $data);
        $this->assertArrayHasKey('built', $data);
        $this->assertArrayHasKey('fashioned', $data);
    }

    //| get weapon not found test

    public function test_get_weapon_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->get(route('api.arsenal.armory.item.get', ['uuid' => $this->getFalseUuid(UserWeapon::class), 'type' => 'weapon']));

        $response->assertNotFound();
        $this->assertApiMessage('Item not found', $response);
    }

    //| get companion not found test

    public function test_get_companion_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->get(route('api.arsenal.armory.item.get', ['uuid' => $this->getFalseUuid(UserCompanion::class), 'type' => 'companion']));

        $response->assertNotFound();
        $this->assertApiMessage('Item not found', $response);
    }
}
