<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Finished meals get the quality they turned out at, which their plated portions carry along
     * with when they spoil. Existing meals and portions start without, so nothing already there changes
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->integer('quality')->nullable()->after('portions_left');
        });

        Schema::table('dnd__restaurant_served_meal', function (Blueprint $table) {
            $table->integer('quality')->nullable()->after('order_item_uuid');
            // In the restaurant's in-game time
            $table->dateTime('expires_at')->nullable()->after('quality');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_served_meal', function (Blueprint $table) {
            $table->dropColumn(['quality', 'expires_at']);
        });

        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->dropColumn('quality');
        });
    }
};
