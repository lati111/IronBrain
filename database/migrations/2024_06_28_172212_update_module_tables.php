<?php

use App\Models\Config\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //| Drop foreigns

        Schema::table('nav__submenu', function (Blueprint $table) {
            $table->dropForeign('nav__submenu_project_id_foreign');
            $table->dropForeign('nav__submenu_permission_id_foreign');
        });

        //| Rename tables

        Schema::rename('nav__project', 'module__main');
        Schema::rename('nav__submenu', 'module__sub');

        //| Update main module

        Schema::table('module__main', function (Blueprint $table) {
            $table->string('code', 64)->nullable()->after('id');
            $table->boolean('requires_login')->default(0)->after('in_nav');
            $table->timestamp('deleted_at')->nullable();
            $table->dropColumn('active');
        });

        $modules = DB::connection()->table('module__main')->get();
        foreach($modules as $module)
        {
            DB::connection()->table('module__main')
                ->where('id', $module->id)
                ->update([
                    'code' => strtolower($module->name)
                ]);
        }

        Schema::table('module__main', function (Blueprint $table) {
            $table->string('code', 64)->unique()->nullable(false)->change();
        });

        //| Update sub modules

        Schema::table('module__sub', function (Blueprint $table) {
            $table->integer('module_id')->nullable()->after('id');
            $table->string('code', 64)->nullable()->after('module_id');
            $table->boolean('requires_login')->default(0)->after('permission_id');
            $table->timestamp('deleted_at')->nullable();
        });

        $submodules = DB::connection()->table('module__sub')->get();
        foreach($submodules as $module)
        {
            DB::connection()->table('module__sub')
                ->where('id', $module->id)
                ->update([
                    'module_id' => $module->project_id,
                    'code' => strtolower($module->name)
                ]);
        }

        Schema::table('module__sub', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable(false)->change();
            $table->string('code', 64)->nullable(false)->change();
            $table->dropColumn('project_id');

            $table->foreign('module_id')->references('id')->on('module__main')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('permission_id')->references('id')->on('auth__permission')->onDelete('set null')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //| Revert sub modules
        Schema::table('module__sub', function (Blueprint $table) {
            $table->integer('project_id')->nullable()->after('id');
            $table->dropColumn('code');
            $table->dropColumn('deleted_at');
            $table->dropColumn('requires_login');
        });

        $submodules = DB::connection()->table('module__sub')->get();
        foreach($submodules as $module)
        {
            DB::connection()->table('module__sub')
                ->where('id', $module->id)
                ->update([
                    'project_id' => $module->module_id,
                ]);
        }

        Schema::table('module__sub', function (Blueprint $table) {
            $table->dropForeignIdFor('module__main');
            $table->dropColumn('module_id');
            $table->id('project_id')->change();

            $table->foreign('project_id')->references('id')->on('module__main')->onDelete('cascade')->onUpdate('no action');

        });

        //| Revert main modules

        Schema::table('module__main', function (Blueprint $table) {
            $table->dropColumn('code');
            $table->dropColumn('deleted_at');
            $table->dropColumn('requires_login');
            $table->boolean('active')->default(1)->after('in_nav');
        });

        //| Revert renames

        Schema::rename('module__sub', 'nav__submenu');
        Schema::rename('module__main', 'nav__project');
    }
};
