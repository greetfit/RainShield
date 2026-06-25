<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('salary_type', 20)->default('piece_rate')->after('designation');
            $table->decimal('monthly_salary', 14, 2)->nullable()->after('salary_type');
        });

        Schema::table('job_cards', function (Blueprint $table) {
            $table->decimal('wage_paid_amount', 14, 2)->default(0)->after('wage_amount');
        });

        Schema::create('job_card_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->date('received_on');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedInteger('quantity_damaged')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('wage_amount', 14, 2)->default(0);
            $table->decimal('wage_paid_amount', 14, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_card_receipts');

        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropColumn('wage_paid_amount');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['salary_type', 'monthly_salary']);
        });
    }
};
