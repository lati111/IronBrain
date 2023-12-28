<?php

namespace Tests\Unit;

use App\Models\Auth\User;
use Database\Seeders\AuthSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\Model;
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

    protected function createRandomEntities(string $model, int $amount, array $params = []): Collection {
        $entities = $model::factory()->count($amount)->create($params);
        return $entities;
    }
}
