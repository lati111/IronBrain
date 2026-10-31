<?php

namespace App\Console\Commands\Core;

use App\Console\Commands\Traits\RunsImports;
use App\Models\Auth\Permission;
use App\Models\Config\Module;
use App\Models\Config\Submodule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class ImportModules extends Command
{
    use RunsImports;

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

    /** @var array<string, true> The codes of the modules imported during this run */
    private array $imported = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->imported = [];
        $this->runImport('modules',
            fn () => $this->getModules(),
            fn (array $data) => $this->importModule($data),
            fn () => $this->pruneRemovedModules(),
        );

        return $this->finishImport('Module');
    }

    /**
     * Reads and validates the modules from the json
     * @return array Returns the modules
     */
    private function getModules(): array
    {
        $json = Storage::json('/config/Core/modules.json');
        if (!is_array($json)) {
            throw new InvalidArgumentException('modules.json is missing or contains invalid JSON');
        }

        $validator = Validator::make(['json' => $json], [
            'json' => 'required|array',
            'json.*' => 'required|array',
            'json.*.code' => 'required|string|distinct',
            'json.*.name' => 'required|string',
            'json.*.description' => 'required|string',
            'json.*.thumbnail' => 'nullable|string',
            'json.*.route' => 'nullable|string',
            'json.*.permission' => 'nullable|string',
            'json.*.order' => 'required|integer|distinct',
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
            throw new InvalidArgumentException('modules.json is invalid: ' . implode(' ', $validator->errors()->all()));
        }

        return $json;
    }

    /**
     * Saves a module and its submodules to the database
     * @param array $data The module data
     * @return bool Returns whether the module or any of its submodules changed
     */
    private function importModule(array $data): bool
    {
        $module = Module::withTrashed()->where('code', $data['code'])->first();
        if ($module === null) {
            $module = new Module();
            $module->code = $data['code'];
        }

        // The order is unique, so take it from whichever module has it now. Modules that are still in the json get their new order once imported
        Module::withTrashed()->where('order', $data['order'])->where('code', '!=', $data['code'])->update(['order' => null]);

        $wasRestored = $module->trashed();
        $module->deleted_at = null;
        $module->name = $data['name'];
        $module->description = $data['description'];
        $module->thumbnail = $data['thumbnail'];
        $module->order = $data['order'];
        $module->in_overview = $data['in_overview'];
        $module->in_nav = $data['in_nav'];
        $module->requires_login = $data['requires_login'] ?? false;
        $module->route = $this->validateRoute($data['route'], $data['code']);
        $module->permission_id = $this->getPermissionId($data['permission'], $data['code']);
        $module->save();

        $this->imported[$module->code] = true;
        $changed = $wasRestored || $module->wasRecentlyCreated || $module->wasChanged();

        //| Import submodules
        $importedSubmodules = [];
        foreach ($data['submodules'] ?? [] as $submoduleData) {
            $code = sprintf('%s.%s', $data['code'], $submoduleData['code']);

            $submodule = Submodule::withTrashed()->where('code', $submoduleData['code'])->where('module_id', $module->id)->first();
            if ($submodule === null) {
                $submodule = new Submodule();
                $submodule->module_id = $module->id;
                $submodule->code = $submoduleData['code'];
            }

            $wasRestored = $submodule->trashed();
            $submodule->deleted_at = null;
            $submodule->name = $submoduleData['name'];
            $submodule->order = $submoduleData['order'];
            $submodule->requires_login = $submoduleData['requires_login'] ?? $data['requires_login'] ?? false;
            $submodule->route = $this->validateRoute($submoduleData['route'], $code);
            $submodule->permission_id = $this->getPermissionId($submoduleData['permission'], $code);
            $submodule->save();

            $importedSubmodules[] = $submodule->code;
            $changed = $wasRestored || $submodule->wasRecentlyCreated || $submodule->wasChanged() || $changed;
        }

        // Remove the submodules that were removed from the json
        $removed = Submodule::where('module_id', $module->id)->whereNotIn('code', $importedSubmodules)->delete();

        return $changed || $removed > 0;
    }

    /**
     * Removes the modules that were removed from the json
     * @return int Returns the amount of modules removed
     */
    private function pruneRemovedModules(): int
    {
        if ($this->imported === []) {
            return 0;
        }

        // Free up the unique order of removed modules, so it can be given to other modules
        $removed = Module::whereNotIn('code', array_keys($this->imported));
        $removed->clone()->update(['order' => null]);

        return $removed->delete();
    }

    /**
     * Checks whether a route exists
     * @param ?string $route The name of the route
     * @param string $code The code of the module the route belongs to
     * @return ?string Returns the route
     */
    private function validateRoute(?string $route, string $code): ?string
    {
        if ($route !== null && !Route::has($route)) {
            throw new InvalidArgumentException(sprintf('Route "%s" of module "%s" does not exist.', $route, $code));
        }

        return $route;
    }

    /**
     * Gets the id of a permission
     * @param ?string $permission The permission code
     * @param string $code The code of the module the permission belongs to
     * @return ?int Returns the id of the permission, or null if no permission is required
     */
    private function getPermissionId(?string $permission, string $code): ?int
    {
        if ($permission === null) {
            return null;
        }

        $id = Permission::where('permission', $permission)->value('id');
        if ($id === null) {
            throw new InvalidArgumentException(sprintf('Permission "%s" of module "%s" does not exist. Run import:permissions first.', $permission, $code));
        }

        return $id;
    }
}
