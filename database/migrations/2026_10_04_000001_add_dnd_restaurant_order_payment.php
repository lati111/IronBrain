<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Orders get a tip and a discount, and are paid into the shift total once they are finished
     * rather than when they are placed. Items are eaten one by one
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_order', function (Blueprint $table) {
            // In bits, added to and taken off the price of the items when the order is paid
            $table->decimal('tip', 10, 2)->default(0)->after('total_price');
            $table->decimal('discount', 10, 2)->default(0)->after('tip');
            // When the order's money went into the shift total, in the restaurant's time. Null until it is paid
            $table->dateTime('paid_at')->nullable()->after('fulfilled_at');
        });

        Schema::table('dnd__restaurant_order_item', function (Blueprint $table) {
            // When the portion was eaten, which returns its serving equipment dirty
            $table->dateTime('consumed_at')->nullable()->after('served_at');
        });

        // Orders placed so far were paid into the shift total as they were placed, so they mustn't be paid again
        DB::table('dnd__restaurant_order')->update(['paid_at' => DB::raw('ordered_at')]);

        // Finished orders were eaten in full
        DB::table('dnd__restaurant_order_item')
            ->whereNotNull('served_at')
            ->whereIn('order_uuid', DB::table('dnd__restaurant_order')->where('status', 'finished')->select('uuid'))
            ->update(['consumed_at' => DB::raw('served_at')]);
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_order_item', function (Blueprint $table) {
            $table->dropColumn('consumed_at');
        });

        Schema::table('dnd__restaurant_order', function (Blueprint $table) {
            $table->dropColumn(['tip', 'discount', 'paid_at']);
        });
    }
};
