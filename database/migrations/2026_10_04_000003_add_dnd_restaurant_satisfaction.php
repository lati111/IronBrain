<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Each portion is rated by how satisfied its customer was once it is served. Portions served
     * so far weren't rated, so they stay without
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_order_item', function (Blueprint $table) {
            // From 1 to 5
            $table->unsignedTinyInteger('satisfaction')->nullable()->after('consumed_at');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_order_item', function (Blueprint $table) {
            $table->dropColumn('satisfaction');
        });
    }
};
