<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->unsignedInteger('rejected_quantity')->default(0)->after('completed_quantity');
            $table->text('completion_notes')->nullable()->after('material_cost');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['rejected_quantity', 'completion_notes']);
        });
    }
};
