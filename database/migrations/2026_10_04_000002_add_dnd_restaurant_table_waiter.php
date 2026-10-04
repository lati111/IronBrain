<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Tables get an employee waiting them, who takes their orders. Existing tables start without
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_table', function (Blueprint $table) {
            $table->foreignUuid('employee_uuid')->nullable()->after('room_uuid');
            $table->foreign('employee_uuid')->references('uuid')->on('dnd__restaurant_employee')->nullOnDelete()->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_table', function (Blueprint $table) {
            $table->dropForeign(['employee_uuid']);
            $table->dropColumn('employee_uuid');
        });
    }
};
