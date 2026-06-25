<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_variant_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 12);                   // in | out | adjustment
            $table->decimal('quantity', 14, 3);                // always positive
            $table->decimal('unit_cost', 14, 4);
            $table->nullableMorphs('reference');               // purchase, work_order, manual...
            $table->string('note')->nullable();
            $table->decimal('balance_quantity', 14, 3);        // running balance after this move
            $table->decimal('balance_average_cost', 14, 4);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
