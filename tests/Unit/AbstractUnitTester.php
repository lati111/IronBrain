<?php

namespace Tests\Unit;

use App\Models\Auth\User;
use Database\Seeders\AuthSeeder;
use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

abstract class AbstractUnitTester extends Testcase
{
    protected ?User $user = null;

    use WithFaker;
    use DatabaseTransactions;

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

    /**
     * Generates a uuid for a model that does not exist
     * @param class-string<Model> $model Name of the model you want to generate a fake uuid for
     * @return string Returns the fake uuid.
     */
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

    protected function getFalseIdentifierString(string $model, string $column): string
    {
        $saved_identifier = null;
        while ($saved_identifier === null) {
            $identifier = $this->faker->regexify('[A-Za-z0-9]{12}');
            if ($model::where($column, $identifier)->count() === 0) {
                $saved_identifier = $identifier;
            }
        }

        return $saved_identifier;
    }

    protected function getRandomEntity(string $model): AbstractModel|null
    {
        $qb = $model::select();

        switch($model) {
            case User::class:
                $qb->where('email', '!=', 'admin@test.nl');
                break;
            case \App\Models\Auth\Role::class:
                $qb->where('is_admin', false);
                break;
        }

        return $qb->first();
    }

    protected function createRandomEntity(string $model, array $params = []): AbstractModel {
        $entity = $model::factory()->make($params);
        $entity->save();
        return $entity;
    }

    protected function createRandomEntities(string|Factory $factory, int $amount, array $params = []): Collection {
        if (is_string($factory)) {
            $factory = $factory::factory();
        }

        return $factory->count($amount)->create($params);
    }
}
