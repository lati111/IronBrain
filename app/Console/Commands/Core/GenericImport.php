<?php

namespace App\Console\Commands\Core;

use App\Models\Auth\Permission;
use App\Models\Config\Module;
use App\Models\Config\Submodule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GenericImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports all data';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->call('import:permissions');
        $this->call('import:modules');

        $this->call('import:pksanc');
    }
}
