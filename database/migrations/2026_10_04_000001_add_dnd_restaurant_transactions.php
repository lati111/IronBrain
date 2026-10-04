<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations. Money going in and out of a restaurant is logged as transactions, both the profit of each
     * service and those added by hand. Services that already ended get the transaction of their profit, so the log
     * covers everything paid into the restaurant so far
     */
    public function up(): void
    {
        Schema::create('dnd__restaurant_transaction', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            // What the transaction was: the profit of a service, or one added by hand
            $table->string('type', 32);
            $table->string('title', 255);
            $table->text('description')->nullable();
            // In bits, negative when money went out
            $table->decimal('amount', 12, 2)->default(0);
            // The service the transaction is the profit of
            $table->foreignUuid('service_uuid')->nullable();
            // In the restaurant's in-game time
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['restaurant_uuid', 'occurred_at']);
            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('service_uuid')->references('uuid')->on('dnd__restaurant_service_history')->nullOnDelete()->onUpdate('no action');
        });

        $services = DB::table('dnd__restaurant_service_history')->whereNotNull('ended_at')->orderBy('ended_at')->get();
        foreach ($services as $service) {
            DB::table('dnd__restaurant_transaction')->insert([
                'uuid' => (string) Str::uuid7(),
                'restaurant_uuid' => $service->restaurant_uuid,
                'type' => 'service',
                'title' => 'Daily finances',
                'amount' => $service->profit,
                'service_uuid' => $service->uuid,
                'occurred_at' => $service->ended_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations. The services themselves keep their profit
     */
    public function down(): void
    {
        Schema::dropIfExists('dnd__restaurant_transaction');
    }
};
