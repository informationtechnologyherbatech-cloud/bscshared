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
        Schema::table('financial_ratios', function (Blueprint $table) {
            if (!Schema::hasColumn('financial_ratios', 'benchmark')) {
                $table->string('benchmark', 100)->nullable()->after('target');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_ratios', function (Blueprint $table) {
            $table->dropColumn('benchmark');
        });
    }
};
