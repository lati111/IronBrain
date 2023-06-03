<?php

namespace Database\Factories\Auth;

use App\Models\Auth\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Auth\Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $permission = null;
        while ($permission === null) {
            $perm = $this->faker->regexify('[A-Za-z0-9]{48}');
            if (Permission::where('permission', $perm)->count() === 0) {
                $permission = $perm;
            }
        }

        return [
            'permission' => $permission,
            'name' => $this->faker->regexify('[A-Za-z0-9]{26}'),
            'description' => $this->faker->regexify('[A-Za-z0-9]{48}'),
            'group' => $this->faker->regexify('[A-Za-z0-9]{12}'),
        ];
    }
}
