<?php

namespace App\Console\Commands\Core;

use App\Models\Config\Module;
use App\Models\Config\Submodule;
use App\Service\ChangelogService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateModules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modules:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sets the updated at of the modules and submodules to the date of the latest changelog that touches them';

    /**
     * Execute the console command.
     */
    public function handle(ChangelogService $changelogService): int
    {
        $dates = $changelogService->getLatestChangeDates();

        $updated = 0;
        foreach (Module::all() as $module) {
            if (!isset($dates[$module->name])) {
                continue;
            }

            $updated += $this->setUpdatedAt($module, $dates[$module->name]['date'], $module->name);
            foreach (Submodule::where('module_id', $module->id)->get() as $submodule) {
                if (isset($dates[$module->name]['submodules'][$submodule->name])) {
                    $updated += $this->setUpdatedAt(
                        $submodule,
                        $dates[$module->name]['submodules'][$submodule->name],
                        sprintf('%s / %s', $module->name, $submodule->name),
                    );
                }
            }
        }

        $this->info(sprintf('Updated %d module(s) and submodule(s).', $updated));

        return self::SUCCESS;
    }

    /**
     * Sets when a module or submodule was last updated
     * @param Module|Submodule $module The module or submodule
     * @param string $date The date of the latest changelog touching it
     * @param string $label The name to report it under
     * @return int 1 when it changed, 0 when it already had the date
     */
    private function setUpdatedAt(Module|Submodule $module, string $date, string $label): int
    {
        $updatedAt = Carbon::parse($date)->startOfDay();
        if ($module->updated_at?->equalTo($updatedAt)) {
            return 0;
        }

        $module->updated_at = $updatedAt;
        $module->save();
        $this->line(sprintf('%s: %s', $label, $updatedAt->toDateString()));

        return 1;
    }
}
