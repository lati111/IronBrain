<?php

namespace App\Console\Commands\Core;

use App\Models\Auth\Permission;
use App\Models\Config\Module;
use App\Models\Config\Submodule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ImportModules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:modules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports and updates all modules from the json';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->line('Importing modules...');

        $changedCount = 0;
        $json = Storage::json('/config/Core/modules.json');
        $bar = $this->output->createProgressBar(count($json));
        $bar->start();

        $validator = Validator::make(['json' => $json], [
            'json' => 'required|array',
            'json.*' => 'required|array',
            'json.*.code' => 'required|string',
            'json.*.name' => 'required|string',
            'json.*.description' => 'required|string',
            'json.*.thumbnail' => 'nullable|string',
            'json.*.route' => 'nullable|string',
            'json.*.permission' => 'nullable|string',
            'json.*.order' => 'required|integer',
            'json.*.in_overview' => 'required|boolean',
            'json.*.in_nav' => 'required|boolean',
            'json.*.requires_login' => 'nullable|boolean',
            'json.*.submodules' => 'nullable|array',
            'json.*.submodules.*.code' => 'required|string',
            'json.*.submodules.*.name' => 'required|string',
            'json.*.submodules.*.order' => 'required|integer',
            'json.*.submodules.*.route' => 'nullable|string',
            'json.*.submodules.*.permission' => 'nullable|string',
            'json.*.submodules.*.requires_login' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            dd($validator->errors());
        }

        foreach ($json as $data) {
            //| Import modules
            $module = Module::where('code', $data['code'])->first();
            if ($module === null) {
                $module = new Module();
                $module->code = $data['code'];
            }

            $module->name = $data['name'];
            $module->description = $data['description'];
            $module->thumbnail = $data['thumbnail'];
            $module->order = $data['order'];
            $module->in_overview = $data['in_overview'];
            $module->in_nav = $data['in_nav'];
            $module->requires_login = $data['requires_login'] ?? false;

            $module->route = $data['route'];
            if ($data['route'] === '' && Route::has($data['route']) === false) {
                $this->newLine();
                $this->error(sprintf('Route "%s" not found.', $data['route']));
                return;
            }

            if ($data['permission'] !== null) {
                $permission = Permission::where('permission', $data['permission'])->first();
                if ($permission === null) {
                    $this->newLine();
                    $this->error(sprintf('Permission with code "%s" not found.', $data['permission']));
                    return;
                }

                $module->permission_id = $permission->id;
            } else {
                $module->permission_id = null;
            }

            $module->save();
            if ($module->wasChanged() || $module->wasRecentlyCreated) {
                $changedCount++;
            }

            //| Import submodules
            foreach (($data['submodules'] ?? []) as $submoduleData) {
                $submodule = Submodule::where('code', $submoduleData['code'])->where('module_id', $module->id)->first();
                if ($submodule === null) {
                    $submodule = new Submodule();
                    $submodule->module_id = $module->id;
                    $submodule->code = $submoduleData['code'];
                }

                $submodule->name = $submoduleData['name'];
                $submodule->order = $submoduleData['order'];
                $submodule->requires_login = $submoduleData['requires_login'] ?? $data['requires_login'] ?? false;

                $submodule->route = $submoduleData['route'];
                if ($submoduleData['route'] === '' && Route::has($submoduleData['route']) === false) {
                    $this->newLine();
                    $this->error(sprintf('Route "%s" not found.', $submoduleData['route']));
                    return;
                }

                if ($submoduleData['permission'] !== null) {
                    $permission = Permission::where('permission', $submoduleData['permission'])->first();
                    if ($permission === null) {
                        $this->newLine();
                        $this->error(sprintf('Permission with code "%s" not found.', $submoduleData['permission']));
                        return;
                    }

                    $submodule->permission_id = $permission->id;
                } else {
                    $submodule->permission_id = null;
                }

                $submodule->save();
                if ($submodule->wasChanged() || $submodule->wasRecentlyCreated) {
                    $changedCount++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line($changedCount . ' modules imported.');
    }
}
