<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projectJSON = file_get_contents(__DIR__."../../../tests/Data/Config/project.json");
        $projectData = json_decode($projectJSON, true);

        $project_table = DB::table('nav__project');
        foreach($projectData as $data) {
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
    }
}
