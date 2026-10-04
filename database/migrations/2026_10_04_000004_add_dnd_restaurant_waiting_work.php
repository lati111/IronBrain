<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Waiters get jobs of their own in the work queue: taking a table's order, and bringing a
     * plated portion to it. Existing prep and cleaning jobs aren't tied to a table, so they stay as they are
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            // The table the job is for, done by whoever waits it
            $table->foreignUuid('table_uuid')->nullable()->after('equipment_uuid');
            // The order being taken, or the plated portion being brought
            $table->foreignUuid('order_uuid')->nullable()->after('table_uuid');
            $table->foreignUuid('served_meal_uuid')->nullable()->after('order_uuid');

            $table->foreign('table_uuid')->references('uuid')->on('dnd__restaurant_table')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('order_uuid')->references('uuid')->on('dnd__restaurant_order')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('served_meal_uuid')->references('uuid')->on('dnd__restaurant_served_meal')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations. Waiters' jobs can't exist without these, so they are removed first
     */
    public function down(): void
    {
        DB::table('dnd__restaurant_work')->where('station', 'waiting')->delete();

        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            $table->dropForeign(['table_uuid']);
            $table->dropForeign(['order_uuid']);
            $table->dropForeign(['served_meal_uuid']);
            $table->dropColumn(['table_uuid', 'order_uuid', 'served_meal_uuid']);
        });
    }
};
