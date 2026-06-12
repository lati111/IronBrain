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

class UpdateItemTest extends AbstractApiFeatureTester implements PostOperationInterface
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
        return route('api.arsenal.armory.item.update');
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

    public function test_forma_validation(): void
    {
        $base = ['uuid' => $this->userWarframe->uuid, 'type' => 'warframe'];

        //is integer
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), array_merge($base, ['forma' => 'not_an_int']));
        $this->assertEquals(400, $response->status());
        $this->assertContains('The forma field must be an integer.', $response->json()['errors']['forma']);

        //min 0
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), array_merge($base, ['forma' => -1]));
        $this->assertEquals(400, $response->status());
        $this->assertContains('The forma field must be at least 0.', $response->json()['errors']['forma']);

        //max 10
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), array_merge($base, ['forma' => 11]));
        $this->assertEquals(400, $response->status());
        $this->assertContains('The forma field must not be greater than 10.', $response->json()['errors']['forma']);

        //nullable - omitting is valid
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $base);
        $this->assertEmpty($response->json()['errors']['forma'] ?? []);
    }

    public function test_shards_validation(): void
    {
        $base = ['uuid' => $this->userWarframe->uuid, 'type' => 'warframe'];

        //is integer
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), array_merge($base, ['shards' => 'not_an_int']));
        $this->assertEquals(400, $response->status());
        $this->assertContains('The shards field must be an integer.', $response->json()['errors']['shards']);

        //min 0
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), array_merge($base, ['shards' => -1]));
        $this->assertEquals(400, $response->status());
        $this->assertContains('The shards field must be at least 0.', $response->json()['errors']['shards']);

        //max 5
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), array_merge($base, ['shards' => 6]));
        $this->assertEquals(400, $response->status());
        $this->assertContains('The shards field must not be greater than 5.', $response->json()['errors']['shards']);
    }

    public function test_school_validation(): void
    {
        $base = ['uuid' => $this->userWarframe->uuid, 'type' => 'warframe'];

        //invalid school
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), array_merge($base, ['school' => 'invalid_school']));
        $this->assertEquals(400, $response->status());
        $this->assertContains('The selected school is invalid.', $response->json()['errors']['school']);

        //nullable - omitting is valid
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $base);
        $this->assertEmpty($response->json()['errors']['school'] ?? []);
    }

    public function test_name_validation(): void
    {
        $base = ['uuid' => $this->userWarframe->uuid, 'type' => 'warframe'];

        //max 255
        $this->assertMaxValidation('string', 'name', 255);

        //nullable - omitting is valid
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $base);
        $this->assertEmpty($response->json()['errors']['name'] ?? []);
    }

    //| item not found tests

    public function test_update_warframe_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'uuid' => $this->getFalseUuid(UserWarframe::class),
                'type' => 'warframe',
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Item not found', $response);
    }

    public function test_update_weapon_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'uuid' => $this->getFalseUuid(UserWeapon::class),
                'type' => 'weapon',
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Item not found', $response);
    }

    public function test_update_companion_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'uuid' => $this->getFalseUuid(UserCompanion::class),
                'type' => 'companion',
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Item not found', $response);
    }

    //| save tests

    public function test_update_warframe(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'uuid'      => $this->userWarframe->uuid,
                'type'      => 'warframe',
                'name'      => 'My Ash',
                'forma'     => 5,
                'shards'    => 3,
                'potato'    => 1,
                'built'     => 1,
                'exilus'    => 1,
                'fashioned' => 1,
                'school'    => 'naramon',
            ]);

        $response->assertOk();
        $this->assertApiMessage('Saved', $response);

        $updated = UserWarframe::where('uuid', $this->userWarframe->uuid)->first();
        $this->assertEquals('My Ash', $updated->name);
        $this->assertEquals(5, $updated->forma);
        $this->assertEquals(3, $updated->shards);
        $this->assertTrue((bool) $updated->potato);
        $this->assertEquals('naramon', $updated->school);
    }

    public function test_update_weapon(): void
    {
        $user       = $this->getAdminUser();
        $weapon     = $this->createTestWeapon();
        $userWeapon = $this->createUserWeapon($user->uuid, $weapon->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'uuid'   => $userWeapon->uuid,
                'type'   => 'weapon',
                'name'   => 'My Weapon',
                'forma'  => 3,
                'potato' => 1,
                'built'  => 1,
                'exilus' => 1,
                'riven'  => 1,
                'school' => 'zenurik',
            ]);

        $response->assertOk();
        $this->assertApiMessage('Saved', $response);

        $updated = UserWeapon::where('uuid', $userWeapon->uuid)->first();
        $this->assertEquals('My Weapon', $updated->name);
        $this->assertEquals(3, $updated->forma);
        $this->assertTrue((bool) $updated->riven);
    }

    public function test_update_companion(): void
    {
        $user          = $this->getAdminUser();
        $companion     = $this->createTestCompanion();
        $userCompanion = $this->createUserCompanion($user->uuid, $companion->id);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'uuid'      => $userCompanion->uuid,
                'type'      => 'companion',
                'name'      => 'My Companion',
                'forma'     => 2,
                'potato'    => 1,
                'built'     => 1,
                'fashioned' => 1,
            ]);

        $response->assertOk();
        $this->assertApiMessage('Saved', $response);

        $updated = UserCompanion::where('uuid', $userCompanion->uuid)->first();
        $this->assertEquals('My Companion', $updated->name);
        $this->assertEquals(2, $updated->forma);
        $this->assertTrue((bool) $updated->fashioned);
    }

    //| nullable name clears to null test

    public function test_update_warframe_clears_name_when_empty(): void
    {
        $this->userWarframe->name = 'Existing Name';
        $this->userWarframe->save();

        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'uuid' => $this->userWarframe->uuid,
                'type' => 'warframe',
                'name' => '',
            ]);

        $updated = UserWarframe::where('uuid', $this->userWarframe->uuid)->first();
        $this->assertNull($updated->name);
    }
}
