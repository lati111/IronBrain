<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. A recipe can be a variant of another, adding to or removing from what the base recipe takes
     * and adding to its stats. The changes are kept apart, while its own columns and components hold the outcome, so
     * the rest of the restaurant uses a variant as any other recipe. Existing recipes are no variant, so stay as they
     * are. Should a base recipe be removed directly, its variants keep what they last were as recipes of their own
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_recipe', function (Blueprint $table) {
            $table->foreignUuid('parent_uuid')->nullable()->after('restaurant_uuid');
            $table->json('variant_changes')->nullable()->after('expires_in');
            $table->foreign('parent_uuid')->references('uuid')->on('dnd__restaurant_recipe')->nullOnDelete()->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_recipe', function (Blueprint $table) {
            $table->dropForeign(['parent_uuid']);
            $table->dropColumn(['parent_uuid', 'variant_changes']);
        });
    }
};
