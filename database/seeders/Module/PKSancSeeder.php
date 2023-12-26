<?php

namespace Database\Seeders\Module;

use App\Models\Auth\Permission;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PKSancSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gamesJSON = file_get_contents(__DIR__."../../../../tests/Data/Modules/PKSanc/games.json");
        $gamesData = json_decode($gamesJSON, true);

        $games_table = DB::table('pksanc__game');
        foreach($gamesData as $data) {
            $games_table->insert(array_merge($data, [
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]));
        }
    }
}
