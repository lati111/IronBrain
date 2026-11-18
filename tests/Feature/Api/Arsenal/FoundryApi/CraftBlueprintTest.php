<?php

namespace Tests\Feature\Api\Arsenal\FoundryApi;

use App\Models\Arsenal\Component;
use App\Models\Arsenal\UserComponent;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\Warframe;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class CraftBlueprintTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, ArsenalTestHelper;

    private Warframe   $warframe;
    private Component  $component;

    public function setUp(): void
    {
        parent::setUp();
        $user            = $this->getAdminUser();
        $this->warframe  = $this->createTestWarframe();
        $this->component = $this->createTestComponent($this->warframe->id, 1);
        $this->createUserComponent($user->uuid, $this->component->uuid, 1);
    }

    protected function getRoute(): string
    {
        return route('api.arsenal.foundry.blueprint.craft');
    }

    protected function getModel(): Warframe
    {
        return new Warframe();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'blueprint_id' => $this->warframe->id,
            'type'         => 'warframe',
        ], $overwrites);
    }

    //| validation tests

    public function test_blueprint_id_validation(): void
    {
        $this->assertRequiredValidation('blueprint_id', 'blueprint id');
        $this->assertStringValidation('blueprint_id', 'blueprint id');
    }

    public function test_type_validation(): void
    {
        $this->assertRequiredValidation('type');
        $this->assertStringValidation('type');
        $this->assertInArrayValidation('type', 'invalid_type');
    }

    //| blueprint not found test (no components exist for that id)

    public function test_blueprint_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'blueprint_id' => 'nonexistent_blueprint',
                'type'         => 'warframe',
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Blueprint not found', $response);
    }

    //| blueprint not complete (missing user component) test

    public function test_blueprint_not_complete_missing_component(): void
    {
        $warframe   = $this->createTestWarframe();
        $component  = $this->createTestComponent($warframe->id, 3);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'blueprint_id' => $warframe->id,
                'type'         => 'warframe',
            ]);

        $response->assertForbidden();
        $this->assertApiMessage('Blueprint is not complete', $response);
    }

    //| blueprint not complete (insufficient amount) test

    public function test_blueprint_not_complete_insufficient_amount(): void
    {
        $user      = $this->getAdminUser();
        $warframe  = $this->createTestWarframe();
        $component = $this->createTestComponent($warframe->id, 3);
        $this->createUserComponent($user->uuid, $component->uuid, 1);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'blueprint_id' => $warframe->id,
                'type'         => 'warframe',
            ]);

        $response->assertForbidden();
        $this->assertApiMessage('Blueprint is not complete', $response);
    }

    //| craft success test

    public function test_craft_success(): void
    {
        $user = $this->getAdminUser();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'blueprint_id' => $this->warframe->id,
                'type'         => 'warframe',
            ]);

        $response->assertOk();
        $this->assertApiMessage('Blueprint crafted', $response);

        $this->assertNotNull(
            UserWarframe::where('id', $this->warframe->id)->where('owner_uuid', $user->uuid)->first()
        );
        $this->assertNull(
            UserComponent::where('id', $this->component->uuid)->where('owner_uuid', $user->uuid)->first()
        );
    }

    //| grantItem - base item not found test

    public function test_craft_base_item_not_found(): void
    {
        $user       = $this->getAdminUser();
        $fakeId     = 'ghost_' . \Illuminate\Support\Str::random(8);
        $component  = $this->createTestComponent($fakeId, 1);
        $this->createUserComponent($user->uuid, $component->uuid, 1);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'blueprint_id' => $fakeId,
                'type'         => 'warframe',
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Warframe not found', $response);
    }

    //| craft weapon blueprint test

    public function test_craft_weapon_blueprint(): void
    {
        $user      = $this->getAdminUser();
        $weapon    = $this->createTestWeapon();
        $component = $this->createTestComponent($weapon->id, 1);
        $this->createUserComponent($user->uuid, $component->uuid, 1);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'blueprint_id' => $weapon->id,
                'type'         => 'weapon',
            ]);

        $response->assertOk();
        $this->assertNotNull(
            \App\Models\Arsenal\UserWeapon::where('id', $weapon->id)->where('owner_uuid', $user->uuid)->first()
        );
    }

    //| craft companion blueprint test

    public function test_craft_companion_blueprint(): void
    {
        $user      = $this->getAdminUser();
        $companion = $this->createTestCompanion();
        $component = $this->createTestComponent($companion->id, 1);
        $this->createUserComponent($user->uuid, $component->uuid, 1);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'blueprint_id' => $companion->id,
                'type'         => 'companion',
            ]);

        $response->assertOk();
        $this->assertNotNull(
            \App\Models\Arsenal\UserCompanion::where('id', $companion->id)->where('owner_uuid', $user->uuid)->first()
        );
    }
}
