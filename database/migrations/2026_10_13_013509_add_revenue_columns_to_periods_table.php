<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->decimal('target_revenue', 15, 2)->default(120.00)->after('apex_score'); // Miliar IDR
            $table->decimal('actual_revenue', 15, 2)->default(115.20)->after('target_revenue'); // Miliar IDR
            $table->decimal('weight_revenue', 5, 2)->default(45.00)->after('actual_revenue'); // Bobot %
            $table->decimal('weight_ratios', 5, 2)->default(55.00)->after('weight_revenue'); // Bobot %
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn(['target_revenue', 'actual_revenue', 'weight_revenue', 'weight_ratios']);
        });
    }
};