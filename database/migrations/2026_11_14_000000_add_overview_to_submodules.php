<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Submodules can be shown in the home overview, grouped under their main module
     */
    public function up(): void
    {
        Schema::table('module__sub', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('thumbnail')->nullable()->after('description');
            $table->boolean('in_overview')->default(false)->after('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module__sub', function (Blueprint $table) {
            $table->dropColumn(['description', 'thumbnail', 'in_overview']);
        });
    }
};
