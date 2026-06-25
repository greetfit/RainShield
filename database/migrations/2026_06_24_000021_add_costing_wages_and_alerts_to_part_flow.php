<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_stock_balances', function (Blueprint $table) {
            $table->decimal('average_cost', 12, 4)->default(0)->after('quantity');
            $table->unsignedInteger('alert_quantity')->default(0)->after('average_cost');
        });

        Schema::table('recoverable_part_balances', function (Blueprint $table) {
            $table->decimal('average_cost', 12, 4)->default(0)->after('quantity');
            $table->unsignedInteger('alert_quantity')->default(0)->after('average_cost');
        });

        Schema::table('part_stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 4)->default(0)->after('quantity');
            $table->decimal('balance_average_cost', 12, 4)->default(0)->after('balance_quantity');
        });

        Schema::table('cutting_batches', function (Blueprint $table) {
            $table->decimal('piece_rate', 12, 2)->default(0)->after('material_quantity');
            $table->decimal('wage_amount', 12, 2)->default(0)->after('piece_rate');
            $table->decimal('wage_paid_amount', 12, 2)->default(0)->after('wage_amount');
        });

        Schema::table('recovery_cuttings', function (Blueprint $table) {
            $table->decimal('piece_rate', 12, 2)->default(0)->after('scrap_quantity');
            $table->decimal('wage_amount', 12, 2)->default(0)->after('piece_rate');
            $table->decimal('wage_paid_amount', 12, 2)->default(0)->after('wage_amount');
        });

        Schema::table('work_order_parts', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 4)->default(0)->after('quantity');
            $table->decimal('total_cost', 12, 2)->default(0)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_parts', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_cost']);
        });

        Schema::table('recovery_cuttings', function (Blueprint $table) {
            $table->dropColumn(['piece_rate', 'wage_amount', 'wage_paid_amount']);
        });

        Schema::table('cutting_batches', function (Blueprint $table) {
            $table->dropColumn(['piece_rate', 'wage_amount', 'wage_paid_amount']);
        });

        Schema::table('part_stock_movements', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'balance_average_cost']);
        });

        Schema::table('recoverable_part_balances', function (Blueprint $table) {
            $table->dropColumn(['average_cost', 'alert_quantity']);
        });

        Schema::table('part_stock_balances', function (Blueprint $table) {
            $table->dropColumn(['average_cost', 'alert_quantity']);
        });
    }
};
