<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Config\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermission();
        $this->seedProject();
        $this->seedRole();
    }

    private function seedPermission(): void {
        $permission_table = DB::table('auth__permission');

        $permission_table->insert([
            "permission" => "config.project.view",
            "name" => "View Project Config",
            "description" => "Allows the user to see the project config",
            "group" => "project",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $permission_table->insert([
            "permission" => "config.project.edit",
            "name" => "Edit Project Config",
            "description" => "Allows the user to edit the project config",
            "group" => "project",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $permission_table->insert([
            "permission" => "config.user.view",
            "name" => "View User Config",
            "description" => "Allows the user to see the user config",
            "group" => "user",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $permission_table->insert([
            "permission" => "config.user.edit",
            "name" => "Edit User Config",
            "description" => "Allows the user to edit the user config",
            "group" => "user",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $permission_table->insert([
            "permission" => "config.role.view",
            "name" => "View Role Config",
            "description" => "Allows the user to see the role config",
            "group" => "role",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $permission_table->insert([
            "permission" => "config.role.edit",
            "name" => "Edit Role Config",
            "description" => "Allows the user to edit the role config",
            "group" => "role",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $permission_table->insert([
            "permission" => "config.permission.view",
            "name" => "View Permission Config",
            "description" => "Allows the user to see the permission config",
            "group" => "permission",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $permission_table->insert([
            "permission" => "config.permission.edit",
            "name" => "Edit Permission Config",
            "description" => "Allows the user to edit the permission config",
            "group" => "permission",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function seedProject(): void {
        $project_table = DB::table('nav__project');
        $submenu_table = DB::table('nav__submenu');

        $project_table->insert([
            "name" => "Config",
            "description" => "The wrapper for the various configs used to configure the website",
            "order" => 999,
            "in_overview" => false,
            "in_nav" => true,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $configProject = Project::where('name', 'Config')->first();

        $submenu_table->insert([
            'project_id' => $configProject->id,
            'permission_id' => Permission::where('permission', 'config.project.view')->first()->id,
            'route' => 'config.projects.overview',
            "name" => "Project",
            "order" => 1,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $submenu_table->insert([
            'project_id' => $configProject->id,
            'permission_id' => Permission::where('permission', 'config.user.view')->first()->id,
            'route' => 'config.user.overview',
            "name" => "User",
            "order" => 2,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $submenu_table->insert([
            'project_id' => $configProject->id,
            'permission_id' => Permission::where('permission', 'config.role.view')->first()->id,
            'route' => 'config.role.overview',
            "name" => "Role",
            "order" => 3,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $submenu_table->insert([
            'project_id' => $configProject->id,
            'permission_id' => Permission::where('permission', 'config.permission.view')->first()->id,
            'route' => 'config.permission.overview',
            "name" => "Permission",
            "order" => 4,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function seedRole(): void {
        $role_table = DB::table('auth__role');

        $role_table->insert([
            "name" => "Admin",
            "description" => "The role given to administrators which allows full access to the site.",
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'is_admin' => true,
        ]);
    }
}
