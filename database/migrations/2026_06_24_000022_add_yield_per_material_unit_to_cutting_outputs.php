<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutting_batch_outputs', function (Blueprint $table) {
            $table->decimal('yield_per_material_unit', 12, 3)->nullable()->after('part_id');
        });
    }

    public function down(): void
    {
        Schema::table('cutting_batch_outputs', function (Blueprint $table) {
            $table->dropColumn('yield_per_material_unit');
        });
    }
};
