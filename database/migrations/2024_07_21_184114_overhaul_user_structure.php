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
        Schema::table('auth__user', function (Blueprint $table) {
            $table->renameColumn('name', 'username');
            $table->string('email')->nullable()->change();
            $table->dropColumn('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth__user', function (Blueprint $table) {
            $table->renameColumn('username', 'name');
            $table->string('email')->nullable(false)->change();
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->boolean('active')->default(1)->after('remember_token');
        });
    }
};
