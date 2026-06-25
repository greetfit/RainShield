<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A batch of work issued to a staff member at a given stage.
        Schema::create('job_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);                       // cutting | stitching | packing
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->unsignedInteger('quantity_issued');
            $table->unsignedInteger('quantity_received')->nullable(); // null until completed
            $table->decimal('piece_rate', 10, 2)->default(0);
            $table->decimal('wage_amount', 14, 2)->nullable();        // received * rate
            $table->string('status', 20)->default('issued');         // issued | completed
            $table->date('issued_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_cards');
    }
};
