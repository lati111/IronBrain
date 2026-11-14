<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The timeline module's tables: timelines, the categories of a timeline, and the events,
     * timespans and periods that make up a timeline
     */
    public function up(): void
    {
        //| Timeline
        Schema::create('dnd__timeline_timeline', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('owner_uuid');
            $table->string('name', 64);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('owner_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__timeline_category', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('timeline_uuid');
            $table->string('name', 64);
            $table->text('description')->nullable();
            // As #RRGGBB
            $table->char('color', 7);
            $table->timestamps();

            $table->unique(['timeline_uuid', 'name']);
            $table->foreign('timeline_uuid')->references('uuid')->on('dnd__timeline_timeline')->onDelete('cascade')->onUpdate('no action');
        });

        //| Items
        // Years are signed, so a timeline can run from before its world's year zero
        Schema::create('dnd__timeline_event', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('timeline_uuid');
            $this->addItemColumns($table);
            $table->integer('year');
            $table->timestamps();

            $table->index(['timeline_uuid', 'year']);
            $this->addItemForeigns($table);
        });

        Schema::create('dnd__timeline_timespan', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('timeline_uuid');
            $this->addItemColumns($table);
            $table->integer('start_year');
            $table->integer('end_year');
            $table->timestamps();

            $table->index(['timeline_uuid', 'start_year']);
            $this->addItemForeigns($table);
        });

        // Works the same as a timespan, but covers a wider period of time
        Schema::create('dnd__timeline_period', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('timeline_uuid');
            $this->addItemColumns($table);
            $table->integer('start_year');
            $table->integer('end_year');
            $table->timestamps();

            $table->index(['timeline_uuid', 'start_year']);
            $this->addItemForeigns($table);
        });
    }

    /**
     * Adds the columns all timeline items share
     * @param Blueprint $table The table of the item
     */
    private function addItemColumns(Blueprint $table): void
    {
        // Left empty when the category is deleted, as deleting the items along with it would lose them unnoticed
        $table->foreignUuid('category_uuid')->nullable();
        $table->string('name', 128);
        $table->text('description')->nullable();
        // A comma separated list
        $table->text('tags')->nullable();
        // Where the item comes from, which is kept out of the UI
        $table->text('source')->nullable();
    }

    /**
     * Adds the foreign keys all timeline items share
     * @param Blueprint $table The table of the item
     */
    private function addItemForeigns(Blueprint $table): void
    {
        $table->foreign('timeline_uuid')->references('uuid')->on('dnd__timeline_timeline')->onDelete('cascade')->onUpdate('no action');
        $table->foreign('category_uuid')->references('uuid')->on('dnd__timeline_category')->onDelete('set null')->onUpdate('no action');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dnd__timeline_period');
        Schema::dropIfExists('dnd__timeline_timespan');
        Schema::dropIfExists('dnd__timeline_event');
        Schema::dropIfExists('dnd__timeline_category');
        Schema::dropIfExists('dnd__timeline_timeline');
    }
};
