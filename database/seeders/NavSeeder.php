<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Config\Module;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NavSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $navJSON = file_get_contents(__DIR__."../../../tests/Data/Config/nav_main.json");
        $navData = json_decode($navJSON, true);

        $project_table = DB::table('nav__project');
        foreach($navData as $data) {
            if ($data['permission'] !== null) {
                $data['permission_id'] = Permission::where('permission', $data['permission'])->first()->id;
            } else {
                $data['permission_id'] = null;
            }
            unset($data['permission']);

            $project_table->insert(array_merge($data, [
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]));
        }

        $submenuJSON = file_get_contents(__DIR__."../../../tests/Data/Config/nav_submenu.json");
        $submenuData = json_decode($submenuJSON, true);

        $submenu_table = DB::table('nav__submenu');
        foreach($submenuData as $data) {
            if ($data['permission'] !== null) {
                $data['permission_id'] = Permission::where('permission', $data['permission'])->first()->id;
            } else {
                $data['permission_id'] = null;
            }
            unset($data['permission']);

            $data['project_id'] = Module::where('name', $data['parent_name'])->first()->id;
            unset($data['parent_name']);

            unset($data['description']);

            $submenu_table->insert(array_merge($data, [
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]));
        }
    }
}
