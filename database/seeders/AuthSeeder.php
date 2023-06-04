<?php

namespace Database\Seeders;

use App\Models\Auth\Role;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthSeeder extends Seeder
{
    private $user_table;
    private $role_table;
    private $permission_table;
    private $role_permission_table;

    public function run(): void
    {
        $this->user_table = DB::table('auth__user');
        $this->role_table = DB::table('auth__role');
        $this->permission_table = DB::table('auth__permission');
        $this->role_permission_table = DB::table('auth__role_permission');

        $this->seedRole();
        $this->seedPermissions();
        $this->seedRolePermissions();
        $this->seedUser();
    }

    private function seedUser()
    {
        $this->user_table->insert([
            'uuid' => Str::uuid(),
            'name' => "Tester",
            'email' => "test@test.nl",
            'password' => Hash::make("Password123"),
            'role_id' => $this->role_table->where('name', 'Tester')->first()->id,
            'profile_picture' => 'test/pfp.png',
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $this->user_table->insert([
            'uuid' => Str::uuid(),
            'name' => "Admin",
            'email' => "admin@test.nl",
            'password' => Hash::make("Password123"),
            'role_id' => Role::where('name', 'Admin')->first()->id,
            'profile_picture' => 'test/pfp.png',
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function seedRole()
    {
        $this->role_table->insert([
            "name" => "Tester",
            "description" => "Default test role",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
        $this->role_table->insert([
            "name" => "Admin",
            "description" => "Admin test role",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'is_admin' => true,
        ]);
        $this->role_table->insert([
            "name" => "Dummy",
            "description" => "Dummy test role",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function seedPermissions()
    {
        $this->permission_table->insert([
            "permission" => "has.permission",
            "name" => "Given permission",
            "description" => "Test permission that is given to the test user",
            "group" => "test",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $this->permission_table->insert([
            "permission" => "has.not.permission",
            "name" => "Ungiven permission",
            "description" => "Test permission that is not given to the test user",
            "group" => "test",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function seedRolePermissions()
    {
        $this->role_permission_table->insert([
            'role_id' => $this->role_table->where('name', 'Tester')->first()->id,
            'permission_id' => $this->permission_table->where('permission', 'has.permission')->first()->id,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }
}
