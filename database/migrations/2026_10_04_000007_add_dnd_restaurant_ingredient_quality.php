<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Ingredients get a quality, which meals made with them add on top of how well they turned
     * out, and can be variants of another ingredient to use instead of it. Existing ingredients are of quality 0, so
     * meals made so far keep the quality they are served at
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_ingredient', function (Blueprint $table) {
            $table->foreignUuid('parent_uuid')->nullable()->after('restaurant_uuid');
            $table->integer('quality')->default(0)->after('size');
            $table->foreign('parent_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->nullOnDelete()->onUpdate('no action');
        });

        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->integer('ingredient_quality')->default(0)->after('quality_bonus');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->dropColumn('ingredient_quality');
        });

        Schema::table('dnd__restaurant_ingredient', function (Blueprint $table) {
            $table->dropForeign(['parent_uuid']);
            $table->dropColumn(['parent_uuid', 'quality']);
        });
    }
};
