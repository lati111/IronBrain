<?php

namespace App\Console\Commands\Core;

use App\Console\Commands\Traits\RunsImports;
use App\Models\Auth\Permission;
use App\Models\Auth\RolePermission;
use App\Models\Config\Module;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class ImportPermissions extends Command
{
    use RunsImports;

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

    /** @var array<string, true> The permissions imported during this run */
    private array $imported = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->imported = [];
        $this->runImport('permissions',
            fn () => $this->getPermissions(),
            fn (array $data) => $this->importPermission($data),
            fn () => $this->pruneRemovedPermissions(),
        );

        return $this->finishImport('Permission');
    }

    /**
     * Reads and validates the permissions from the json
     * @return array Returns the permissions, each with their group
     */
    private function getPermissions(): array
    {
        $json = Storage::json('/config/Core/permissions.json');
        if (!is_array($json)) {
            throw new InvalidArgumentException('permissions.json is missing or contains invalid JSON');
        }

        $validator = Validator::make($json, [
            '*' => 'required|array',
            '*.*' => 'required|array',
            '*.*.permission' => 'required|string',
            '*.*.name' => 'required|string',
            '*.*.description' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException('permissions.json is invalid: ' . implode(' ', $validator->errors()->all()));
        }

        $permissions = [];
        foreach ($json as $group => $groupPermissions) {
            foreach ($groupPermissions as $data) {
                $permissions[] = [...$data, 'group' => $group];
            }
        }

        return $permissions;
    }

    /**
     * Saves a permission to the database
     * @param array $data The permission data
     * @return bool Returns whether the permission changed
     */
    private function importPermission(array $data): bool
    {
        $permission = Permission::where('permission', $data['permission'])->first();
        if ($permission === null) {
            $permission = new Permission();
            $permission->permission = $data['permission'];
        }

        $permission->name = $data['name'];
        $permission->description = $data['description'];
        $permission->group = $data['group'];
        $permission->save();

        $this->imported[$permission->permission] = true;
        return $permission->wasRecentlyCreated || $permission->wasChanged();
    }

    /**
     * Deletes the permissions that were removed from the json, along with the roles they were granted to
     * @return int Returns the amount of permissions deleted
     */
    private function pruneRemovedPermissions(): int
    {
        if ($this->imported === []) {
            return 0;
        }

        $deleted = 0;
        foreach (Permission::whereNotIn('permission', array_keys($this->imported))->get() as $permission) {
            $modules = Module::withTrashed()->where('permission_id', $permission->id)->pluck('code');
            if ($modules->isNotEmpty()) {
                $this->newLine();
                $this->warn(sprintf('Permission "%s" was removed, but is still required by module(s) %s. Keeping it for now.', $permission->permission, $modules->implode(', ')));
                continue;
            }

            RolePermission::where('permission_id', $permission->id)->delete();
            $permission->delete();
            $deleted++;
        }

        return $deleted;
    }
}
