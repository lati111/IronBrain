<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Saved presets of the tag lists a timeline can be filtered and highlighted by
     */
    public function up(): void
    {
        Schema::create('dnd__timeline_filter_preset', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('timeline_uuid');
            $table->string('name', 64);
            // Comma separated lists of tags: those that hide an item, those an item must all have, and those that highlight it
            $table->text('blacklist')->nullable();
            $table->text('whitelist')->nullable();
            $table->text('highlight')->nullable();
            $table->timestamps();

            $table->unique(['timeline_uuid', 'name']);
            $table->foreign('timeline_uuid')->references('uuid')->on('dnd__timeline_timeline')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dnd__timeline_filter_preset');
    }
};
