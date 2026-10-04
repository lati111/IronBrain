<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Users given access to a restaurant are either managers, with full control as the owner has,
     * or players, who only control the employees assigned to them and move ingredients around. Access granted so far
     * was full access, so it stays that way. Employees can be assigned to a player
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_restaurant_user', function (Blueprint $table) {
            $table->string('role', 16)->default('player')->after('user_uuid');
        });

        DB::table('dnd__restaurant_restaurant_user')->update(['role' => 'manager']);

        Schema::table('dnd__restaurant_employee', function (Blueprint $table) {
            // The player controlling the employee during service, if any
            $table->foreignUuid('player_uuid')->nullable()->after('restaurant_uuid');
            $table->foreign('player_uuid')->references('uuid')->on('auth__user')->nullOnDelete()->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations. Players lose their access, as without roles access is always full access
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_employee', function (Blueprint $table) {
            $table->dropForeign(['player_uuid']);
            $table->dropColumn('player_uuid');
        });

        DB::table('dnd__restaurant_restaurant_user')->where('role', 'player')->delete();

        Schema::table('dnd__restaurant_restaurant_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
