<?php

namespace App\Console\Commands\Core;

use App\Models\Auth\Permission;
use App\Models\Config\Module;
use App\Models\Config\Submodule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ImportPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports and updates all permissions from the json';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->line('Importing permissions...');

        $changedCount = 0;
        $json = Storage::json('/config/Core/permissions.json');
        $bar = $this->output->createProgressBar(count($json));
        $bar->start();

        $validator = Validator::make($json, [
            '*' => 'required|array',
            '*.*' => 'required|array',
            '*.*.permission' => 'required|string',
            '*.*.name' => 'required|string',
            '*.*.description' => 'required|string',
        ]);

        if ($validator->fails()) {
            dd($validator->errors());
        }

        foreach ($json as $group => $permissions) {
            foreach ($permissions as $data) {
                $permission = Permission::where('permission', $data['permission'])->first();
                if ($permission === null) {
                    $permission = new Permission();
                    $permission->permission = $data['permission'];
                }

                $permission->name = $data['name'];
                $permission->description = $data['description'];
                $permission->group = $group;

                $permission->save();
                if ($permission->wasChanged() || $permission->wasRecentlyCreated) {
                    $changedCount++;
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->line($changedCount . ' permissions imported.');
    }
}
