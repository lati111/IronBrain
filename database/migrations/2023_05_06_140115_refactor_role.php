<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::drop('auth__user_role');

        Schema::table('auth__user', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('profile_picture');

            $table->foreign('role_id')->references('id')->on('auth__role')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
