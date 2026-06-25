<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot of materials actually consumed when the work order was released.
        Schema::create('work_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 4);   // weighted-avg cost at the moment of issue
            $table->decimal('total_cost', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_materials');
    }
};
