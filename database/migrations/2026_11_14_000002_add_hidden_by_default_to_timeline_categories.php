<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Categories can be filtered out when a timeline is opened
     */
    public function up(): void
    {
        // Added by hand on some databases before this migration existed
        if (Schema::hasColumn('dnd__timeline_category', 'hidden_by_default')) {
            return;
        }

        Schema::table('dnd__timeline_category', function (Blueprint $table) {
            $table->boolean('hidden_by_default')->default(false)->after('color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('dnd__timeline_category', 'hidden_by_default')) {
            Schema::table('dnd__timeline_category', function (Blueprint $table) {
                $table->dropColumn('hidden_by_default');
            });
        }
    }
};
