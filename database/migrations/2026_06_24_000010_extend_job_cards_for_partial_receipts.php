<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->unsignedInteger('quantity_damaged')->default(0)->after('quantity_received');
            $table->dateTime('started_at')->nullable()->after('issued_on');
            $table->dateTime('completed_at')->nullable()->after('completed_on');
            $table->unsignedInteger('duration_minutes')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropColumn(['quantity_damaged', 'started_at', 'completed_at', 'duration_minutes']);
        });
    }
};
