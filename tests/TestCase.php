<?php

namespace Tests;

use App\Models\AbstractModel;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\RolePermission;
use App\Models\Auth\User;
use Database\Seeders\CoreSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use DatabaseTransactions;
    use WithFaker;

    /** { @inheritDoc } */
    public function setUp(): void
    {
        parent::setUp();
        $this->setUpFaker();
        $this->seed(CoreSeeder::class);
        $this->artisan('import:permissions');
    }

    /**
     * Gets an administrative user if one is available
     * @return User The administrative user
     */
    protected function getAdminUser(): User {
        return User::where('username', '=', 'test_admin')->first();
    }

    /**
     * Gets a non-administrative user with a given set of permissions
     * @param array $permissions An array containing the permissions this user should have
     * @return User The permissions for this user
     */
    protected function getRandomUser(array $permissions = []): User {
        $user = User::where('username', '!=', 'test_admin')->first();

        if (empty($permissions) === false) {
            $role = new Role();
            $role->name = 'TEST';
            $role->description = 'Role made to test permission operations';
            $role->save();

            foreach ($permissions as $permissionString) {
                $permission = Permission::where('permission', $permissionString)->firstOrFail();

                $userPermission = new RolePermission();
                $userPermission->role_id = $role->id;
                $userPermission->permission_id = $permission->id;
                $userPermission->save();
            }

            $user->role_id = $role->id;
            $user->save();
        }

        return $user;
    }

    //| entity manipulation

    /**
     * Generates an invalid uuid for a model
     * @param class-string<AbstractModel> $model The class-string for the model
     * @return string The fake uuid string
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

    /**
     * Gets a random entity belonging to the given model
     * @param string $model The model to retrieve an entity from
     * @return AbstractModel|null The entity, if any
     */
    protected function getRandomEntity(string $model): AbstractModel|null
    {
        /** @var Builder $model */
        $query = $model::select();

        // Exceptions for certain models
        switch($model) {
            case User::class:
                $query->where('username', '!=', 'test_admin');
                break;
        }

        return $query->first();
    }

    /**
     * Create a random entity of the given model
     * @param string $model The model to create an entity for
     * @return AbstractModel The newly created entity
     */
    protected function createRandomEntity(string $model, array $params = []): AbstractModel {
        /** @noinspection PhpUndefinedMethodInspection */
        /** @var Factory $factory */
        $factory = $model::factory();
        $entity = $factory->make($params);
        $entity->save();
        return $entity;
    }

    /**
     * Create a set of random entities using a factory
     * @param string|Factory $factory Either a factory instance, or the model string to create a factory from
     * @param int $amount The amount of entities you want to create for this model
     * @param array $params The parameters to pass to the factory during creation
     * @return Collection A collection of created entities
     */
    protected function createRandomEntities(string|Factory $factory, int $amount, array $params = []): Collection {
        if (is_string($factory)) {
            /** @noinspection PhpUndefinedMethodInspection */
            $factory = $factory::factory();
        }

        return $factory->count($amount)->create($params);
    }

    /**
     * Assert that 2 assosiative arrays match one another
     * @param array $array1 The first array to match
     * @param array $array2 The second array to match
     * @param array $whitelist A list of keys that should be checked to see if the arrays match. Takes array1's keys if none are given
     * @return void
     */
    protected function assertArrayEquals(array $array1, array $array2, array $whitelist = []): void
    {
        $keys = (empty($whitelist) ? array_keys($array1) : $whitelist);
        foreach ($keys as $key) {
            $expected = ($array1[$key] ?? null);
            $actual = ($array2[$key] ?? null);
            $this->assertEquals($expected, $actual, sprintf('Mismatch on key %s', $key));
        }
    }}
