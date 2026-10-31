<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Employees get a capacity, the number of plates they carry to a table in one go, and the
     * jobs of bringing plates can be carried along by the job of the first plate. Every employee starts on 2, and no
     * job is carried along so far, so every plate on its way keeps its own trip
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_employee', function (Blueprint $table) {
            $table->unsignedInteger('capacity')->default(2)->after('prep_speed');
        });

        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            // The job of the trip this plate is carried along on, which does the trip's work for all of them
            $table->foreignUuid('carrier_uuid')->nullable()->after('seat');
            $table->foreign('carrier_uuid')->references('uuid')->on('dnd__restaurant_work')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations. Plates carried along lose their trip, and are brought on their own
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            $table->dropForeign(['carrier_uuid']);
            $table->dropColumn('carrier_uuid');
        });

        Schema::table('dnd__restaurant_employee', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }
};
