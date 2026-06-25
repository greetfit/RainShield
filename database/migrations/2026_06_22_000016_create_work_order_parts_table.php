<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot of the cutting explosion: total pieces to cut per part.
        Schema::create('work_order_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity'); // qty_per_garment * work order quantity
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_parts');
    }
};
