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
        Schema::create('compendium__campaign', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('title', 128);
            $table->text('description')->nullable();
            $table->string('cover_src')->nullable();
            $table->timestamps();
        });

        Schema::create('compendium__player', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('user_uuid');
            $table->foreignUuid('campaign_uuid');
            $table->boolean('is_dm')->default(false);
            $table->timestamps();

            $table->foreign('user_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('campaign_uuid')->references('uuid')->on('compendium__campaign')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('compendium__article', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('player_uuid')->nullable();
            $table->timestamps();

            $table->foreign('player_uuid')->references('uuid')->on('compendium__player')->onDelete('set null')->onUpdate('no action');
        });

        Schema::create('compendium__character', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('campaign_uuid');
            $table->foreignUuid('article_uuid');
            $table->string('name', 128);
            $table->text('titles')->nullable();
            $table->text('description')->nullable();
            $table->string('image_src')->nullable();
            $table->text('tags')->nullable();
            $table->timestamps();

            $table->foreign('campaign_uuid')->references('uuid')->on('compendium__campaign')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('article_uuid')->references('uuid')->on('compendium__article')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('compendium__location', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('parent_location_uuid')->nullable();
            $table->foreignUuid('campaign_uuid');
            $table->foreignUuid('article_uuid');
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->string('map_src')->nullable();
            $table->text('tags')->nullable();
            $table->timestamps();

            $table->foreign('parent_location_uuid')->references('uuid')->on('compendium__location')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('campaign_uuid')->references('uuid')->on('compendium__campaign')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('article_uuid')->references('uuid')->on('compendium__article')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('compendium__article_segment', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('article_uuid');
            $table->string('title');
            $table->text('content');
            $table->integer('order');
            $table->boolean('dm_only')->default(false);
            $table->boolean('private')->default(true);
            $table->timestamps();

            $table->foreign('article_uuid')->references('uuid')->on('compendium__article')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('compendium__article_segment');
        Schema::dropIfExists('compendium__location');
        Schema::dropIfExists('compendium__character');
        Schema::dropIfExists('compendium__player');
        Schema::dropIfExists('compendium__campaign');
        Schema::dropIfExists('compendium__article');
        Schema::disableForeignKeyConstraints();
    }
};
