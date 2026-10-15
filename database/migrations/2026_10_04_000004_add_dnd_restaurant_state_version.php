<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Restaurants count the changes made to them, so pages that refresh can tell when nothing
     * changed without fetching everything again
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_restaurant', function (Blueprint $table) {
            $table->unsignedBigInteger('state_version')->default(0)->after('shift_total');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_restaurant', function (Blueprint $table) {
            $table->dropColumn('state_version');
        });
    }
};
