<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pksanc__stored_pokemon', function (Blueprint $table) {
            $table->timestamp('validated_at')->nullable()->after('owner_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('pksanc__stored_pokemon', function (Blueprint $table) {
            $table->dropColumn('validated_at');
        });
    }
};
