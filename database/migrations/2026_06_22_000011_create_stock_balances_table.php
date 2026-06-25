<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_variant_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('average_cost', 14, 4)->default(0); // weighted-average landed cost
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
