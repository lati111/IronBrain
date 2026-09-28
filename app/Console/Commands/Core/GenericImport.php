<?php

namespace App\Console\Commands\Core;

use Illuminate\Console\Command;

class GenericImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:all {--refresh-images : Download all images again, even if they already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports all data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $failed = [];

        // Modules depend on the permissions, so only import them when the permissions are up to date
        if ($this->call('import:permissions') !== self::SUCCESS) {
            $failed[] = 'permissions';
            $this->warn('Skipping modules, as they depend on the permissions.');
            $failed[] = 'modules';
        } elseif ($this->call('import:modules') !== self::SUCCESS) {
            $failed[] = 'modules';
        }

        $imageOptions = ['--refresh-images' => $this->option('refresh-images')];
        if ($this->call('import:pksanc', $imageOptions) !== self::SUCCESS) {
            $failed[] = 'pksanc';
        }

        if ($this->call('import:arsenal', $imageOptions) !== self::SUCCESS) {
            $failed[] = 'arsenal';
        }

        if ($failed !== []) {
            $this->error('Import finished with errors in: ' . implode(', ', $failed));
            return self::FAILURE;
        }

        $this->info('All imports successful!');
        return self::SUCCESS;
    }
}
