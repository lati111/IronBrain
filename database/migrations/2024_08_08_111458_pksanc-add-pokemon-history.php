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
        Schema::table('pksanc__stored_pokemon', function (Blueprint $table) {
            $table->foreignUuid('prev_uuid')->nullable()->after('owner_uuid');
            $table->integer('version')->default(1)->after('prev_uuid');
            $table->timestamp('deleted_at')->nullable()->after('updated_at');

            $table->foreign('prev_uuid')->references('uuid')->on('pksanc__stored_pokemon')->nullOnDelete()->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pksanc__stored_pokemon', function (Blueprint $table) {
            $table->dropColumn('prev_uuid');
            $table->dropColumn('version');
            $table->dropColumn('deleted_at');
        });
    }
};
