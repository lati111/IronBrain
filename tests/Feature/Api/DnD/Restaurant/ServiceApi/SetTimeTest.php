<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class SetTimeTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '08:00';
        $this->restaurant->save();
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.service.time', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Restaurant
    {
        return new Restaurant();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge(['advance' => 15], $overwrites);
    }

    private function setTime(array $parameters)
    {
        return $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $parameters);
    }

    public function test_advance_or_time_is_required(): void
    {
        $this->setTime([])->assertBadRequest();
    }

    public function test_time_is_advanced(): void
    {
        $response = $this->setTime(['advance' => 75]);

        $response->assertOk();
        $this->assertEquals('09:15', $response->json('data.clock.time'));
        $this->assertEquals('09:15:00', $this->restaurant->fresh()->current_time);
    }

    public function test_advancing_past_midnight_moves_to_the_next_date(): void
    {
        $this->restaurant->current_time = '23:30';
        $this->restaurant->save();

        $response = $this->setTime(['advance' => 60]);

        $response->assertOk();
        $this->assertEquals('1492-03-02', $response->json('data.clock.date'));
        $this->assertEquals('00:30', $response->json('data.clock.time'));
    }

    public function test_time_is_set_on_the_current_date(): void
    {
        $response = $this->setTime(['time' => '13:45']);

        $response->assertOk();
        $this->assertEquals('13:45', $response->json('data.clock.time'));
        $this->assertEquals('1492-03-01', $response->json('data.clock.date'));
    }

    public function test_time_must_be_valid(): void
    {
        $this->setTime(['time' => '25:61'])->assertBadRequest();
        $this->setTime(['advance' => 0])->assertBadRequest();
    }
}
