<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Equipment can take up part of a space and be cleaned in part of a minute. Whole numbers
     * convert to decimals as they are, so existing equipment keeps its size and cleaning time
     */
    public function up(): void
    {
        Schema::table('dnd__restaurant_equipment', function (Blueprint $table) {
            $table->decimal('cleaning_time', 8, 2)->default(0)->change();
            $table->decimal('size', 8, 2)->default(1)->change();
        });
    }

    /**
     * Reverse the migrations. Fractions are rounded to whole numbers, never below the smallest allowed value
     */
    public function down(): void
    {
        DB::table('dnd__restaurant_equipment')->update([
            'cleaning_time' => DB::raw('ROUND(cleaning_time)'),
            'size' => DB::raw('GREATEST(ROUND(size), 1)'),
        ]);

        Schema::table('dnd__restaurant_equipment', function (Blueprint $table) {
            $table->integer('cleaning_time')->default(0)->change();
            $table->integer('size')->default(1)->change();
        });
    }
};
