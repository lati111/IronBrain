<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Customers get a name players see until whoever runs the restaurant reveals the real one, and
     * how they tip. Customers so far were known by their name to everyone, so they start revealed, and none of them
     * tip, so orders are paid as they were
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_customer', function (Blueprint $table) {
            // What players know the customer as, until their name is revealed
            $table->string('hidden_name', 64)->nullable()->after('name');
            $table->boolean('revealed')->default(false)->after('hidden_name');

            // How satisfied, from 1 to 5, a portion has to leave them to tip for it, what they tip for it as a
            // percentage of its price for each satisfaction from there on, and the most they tip for an order, in bits
            $table->unsignedTinyInteger('tip_expectation')->default(3)->after('description');
            $table->decimal('tip_rate', 8, 2)->default(0)->after('tip_expectation');
            $table->decimal('tip_cap', 10, 2)->default(0)->after('tip_rate');
        });

        DB::table('dnd__restaurant_customer')->update(['revealed' => true]);
    }

    /**
     * Reverse the migrations. Tips already paid stay part of their orders
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_customer', function (Blueprint $table) {
            $table->dropColumn(['hidden_name', 'revealed', 'tip_expectation', 'tip_rate', 'tip_cap']);
        });
    }
};
