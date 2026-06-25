<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable();        // supplier invoice no.
            $table->string('supplier_name')->nullable();
            $table->date('purchased_on');
            $table->decimal('transport_charge', 14, 2)->default(0);
            $table->string('allocation_method', 12)->default('value'); // value | quantity
            $table->decimal('items_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0); // items_total + transport
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
