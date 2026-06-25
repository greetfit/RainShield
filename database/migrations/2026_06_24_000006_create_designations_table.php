<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('designation_id')
                ->nullable()
                ->after('phone')
                ->constrained('designations')
                ->nullOnDelete();
        });

        DB::table('staff')
            ->whereNotNull('designation')
            ->where('designation', '<>', '')
            ->select('designation')
            ->distinct()
            ->orderBy('designation')
            ->get()
            ->each(function ($staffDesignation): void {
                $designationId = DB::table('designations')->insertGetId([
                    'name' => $staffDesignation->designation,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('staff')
                    ->where('designation', $staffDesignation->designation)
                    ->update(['designation_id' => $designationId]);
            });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
        });

        Schema::dropIfExists('designations');
    }
};
