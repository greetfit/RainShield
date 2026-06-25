<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_card_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_card_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 14, 2);
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->string('source', 30)->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_card_payments');
    }
};
