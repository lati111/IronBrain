<?php

namespace Tests\Feature\Dataprovider\DnD\Restaurant;

use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use Tests\Feature\AbstractFeatureTester;

class RestaurantOverviewCardlistTest extends AbstractFeatureTester
{
    private function createRestaurant(User $owner, string $name): Restaurant
    {
        $restaurant = new Restaurant();
        $restaurant->owner_uuid = $owner->uuid;
        $restaurant->name = $name;
        $restaurant->date = '1492-03-01';
        $restaurant->opening_time = '08:00';
        $restaurant->closing_time = '22:00';
        $restaurant->save();

        return $restaurant;
    }

    public function test_lists_owned_and_shared_restaurants_only(): void
    {
        $admin = $this->getAdminUser();
        $other = $this->getRandomUser();

        $owned    = $this->createRestaurant($admin, 'Owned Restaurant');
        $shared   = $this->createRestaurant($other, 'Shared Restaurant');
        $unshared = $this->createRestaurant($other, 'Unshared Restaurant');

        $access = new RestaurantUser();
        $access->restaurant_uuid = $shared->uuid;
        $access->user_uuid = $admin->uuid;
        $access->save();

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($admin)
            ->get(route('data.dnd.restaurant.overview', ['perpage' => 30]));

        $response->assertOk();
        $items = collect($response->json('data.items'))->keyBy('uuid');

        $this->assertTrue($items->has($owned->uuid));
        $this->assertTrue($items->has($shared->uuid));
        $this->assertFalse($items->has($unshared->uuid));

        $this->assertEquals(1, $items[$owned->uuid]['is_owner']);
        $this->assertEquals(0, $items[$shared->uuid]['is_owner']);
        $this->assertEquals($other->username, $items[$shared->uuid]['owner_name']);
        $this->assertEquals(route('dnd.restaurant.management', ['restaurant_uuid' => $owned->uuid]), $items[$owned->uuid]['url']);
    }
}
