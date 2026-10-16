<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. A boost on a cooker can be on a single one of its pieces, rather than on all of them.
     * Boosts active so far are on all pieces of their equipment, so they stay that way
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_active_boost', function (Blueprint $table) {
            $table->unsignedInteger('cooker_slot')->nullable()->after('equipment_uuid');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_active_boost', function (Blueprint $table) {
            $table->dropColumn('cooker_slot');
        });
    }
};
