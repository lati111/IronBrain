<?php

namespace Tests\Feature\Api\Arsenal\FoundryApi;

use App\Models\Arsenal\Component;
use App\Models\Arsenal\UserComponent;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class SetComponentAmountTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, ArsenalTestHelper;

    private Component $component;

    public function setUp(): void
    {
        parent::setUp();
        $warframe        = $this->createTestWarframe();
        $this->component = $this->createTestComponent($warframe->id, 5);
    }

    protected function getRoute(): string
    {
        return route('api.arsenal.foundry.component.set');
    }

    protected function getModel(): Component
    {
        return new Component();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'component_uuid' => $this->component->uuid,
            'amount'         => 3,
        ], $overwrites);
    }

    //| validation tests

    public function test_component_uuid_validation(): void
    {
        $this->assertRequiredValidation('component_uuid', 'component uuid');
        $this->assertStringValidation('component_uuid', 'component uuid');
    }

    public function test_amount_validation(): void
    {
        $this->assertRequiredValidation('amount');

        //is integer
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['component_uuid' => $this->component->uuid, 'amount' => 'not_int']);
        $this->assertEquals(400, $response->status());
        $this->assertContains('The amount field must be an integer.', $response->json()['errors']['amount']);

        //min 0
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['component_uuid' => $this->component->uuid, 'amount' => -1]);
        $this->assertEquals(400, $response->status());
        $this->assertContains('The amount field must be at least 0.', $response->json()['errors']['amount']);
    }

    //| component not found test

    public function test_component_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'component_uuid' => $this->getFalseUuid(Component::class),
                'amount'         => 1,
            ]);

        $response->assertNotFound();
        $this->assertApiMessage('Component not found', $response);
    }

    //| set amount to zero deletes user component test

    public function test_set_amount_zero_deletes_user_component(): void
    {
        $user = $this->getAdminUser();
        $this->createUserComponent($user->uuid, $this->component->uuid, 3);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'component_uuid' => $this->component->uuid,
                'amount'         => 0,
            ]);

        $response->assertOk();
        $this->assertNull(
            UserComponent::where('id', $this->component->uuid)->where('owner_uuid', $user->uuid)->first()
        );
    }

    //| set amount creates new user component test

    public function test_set_amount_creates_new_user_component(): void
    {
        $user = $this->getAdminUser();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'component_uuid' => $this->component->uuid,
                'amount'         => 2,
            ]);

        $response->assertOk();
        $userComponent = UserComponent::where('id', $this->component->uuid)->where('owner_uuid', $user->uuid)->first();
        $this->assertNotNull($userComponent);
        $this->assertEquals(2, $userComponent->amount);
    }

    //| set amount updates existing user component test

    public function test_set_amount_updates_existing_user_component(): void
    {
        $user = $this->getAdminUser();
        $this->createUserComponent($user->uuid, $this->component->uuid, 1);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'component_uuid' => $this->component->uuid,
                'amount'         => 4,
            ]);

        $response->assertOk();
        $userComponent = UserComponent::where('id', $this->component->uuid)->where('owner_uuid', $user->uuid)->first();
        $this->assertEquals(4, $userComponent->amount);
    }

    //| amount capped at component maximum test

    public function test_set_amount_capped_at_component_maximum(): void
    {
        $user = $this->getAdminUser();

        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'component_uuid' => $this->component->uuid,
                'amount'         => 999,
            ]);

        $userComponent = UserComponent::where('id', $this->component->uuid)->where('owner_uuid', $user->uuid)->first();
        $this->assertEquals(5, $userComponent->amount);
    }
}
