<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Config\Module;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        User::factory()->make([
            'username' => 'test_admin',
            'role_id' => Role::where('is_admin', '=', true)->first()->id,
        ]);
    }

    private function seedRoles(): void {
        $role_table = DB::table(Role::getTableName());
        $role_table->insert([
            "name" => "Admin",
            "description" => "The role given to administrators which allows full access to the site.",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'is_admin' => true,
        ]);
    }
}
