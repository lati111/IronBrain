<?php

namespace Tests\Unit;

use App\Models\Auth\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

abstract class AbstractUnitTester extends Testcase
{
    protected ?User $user = null;

    use WithFaker;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpFaker();
        $this->seed(AuthSeeder::class);
    }

    protected function getAdminUser(): User {
        return User::where('email', 'admin@test.nl')->first();
    }

    //| entity manipulation
    protected function getFalseUuid(string $model): string
    {
        $saved_uuid = null;
        while ($saved_uuid === null) {
            $uuid = $this->faker->uuid();
            if ($model::where('uuid', $uuid)->count() === 0) {
                $saved_uuid = $uuid;
            }
        }

        return $saved_uuid;
    }

    protected function getFalseId(string $model): int
    {
        $saved_id = null;
        while ($saved_id === null) {
            $id = $this->faker->randomNumber();
            if ($model::where('id', $id)->count() === 0) {
                $saved_id = $id;
            }
        }

        return $saved_id;
    }

    protected function getRandomEntity(string $model): Model|null
    {
        $qb = $model::select();

        switch($model) {
            case User::class:
                $qb->where('email', '!=', 'admin@test.nl');
                break;
        }

        return $qb->first();
    }

    protected function createRandomEntity(string $model): Model {
        $entity = $model::factory()->makeOne();
        $entity->save();
        return $entity;
    }
}
