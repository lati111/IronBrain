<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Customers waiting at the entrance are kept for everyone to see, rather than only by whoever
     * brought them in, and waiters get a job of seating a customer at their table, which takes them a while. Nobody
     * waits at the entrance so far, and jobs so far are about orders and plates, so they seat nobody
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_customer', function (Blueprint $table) {
            // When the customer arrived at the entrance, in the restaurant's time, while they wait there to be seated
            $table->dateTime('entered_at')->nullable()->after('seat');
        });

        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            // The customer being seated, and the seat at the table they are taken to
            $table->foreignUuid('customer_uuid')->nullable()->after('served_meal_uuid');
            $table->unsignedInteger('seat')->nullable()->after('customer_uuid');
            $table->foreign('customer_uuid')->references('uuid')->on('dnd__restaurant_customer')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations. Customers still being seated, or waiting at the entrance, stay where they were
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            $table->dropForeign(['customer_uuid']);
            $table->dropColumn(['customer_uuid', 'seat']);
        });

        Schema::table('dnd__restaurant_customer', function (Blueprint $table) {
            $table->dropColumn('entered_at');
        });
    }
};
