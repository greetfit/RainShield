<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piece_rates', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('stage')->constrained('staff')->cascadeOnDelete();
        });

        Schema::table('piece_rates', function (Blueprint $table) {
            $table->unique(['stage', 'staff_id', 'product_variant_id'], 'piece_rates_stage_staff_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('piece_rates', function (Blueprint $table) {
            $table->dropUnique('piece_rates_stage_staff_variant_unique');
            $table->dropConstrainedForeignId('staff_id');
        });
    }
};
