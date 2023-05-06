<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class initFolders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:init-folders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $paths = [
            'public/img',
            'public/img/profile',
            'public/img/project',
            'public/img/project/thumbnail',
        ];

        foreach($paths as $path) {
            $this->checkPath($path);
        }
    }

    private function checkPath(string $path) {
        if (is_dir($path) === false) {
            File::makeDirectory($path);
        }
    }
}
