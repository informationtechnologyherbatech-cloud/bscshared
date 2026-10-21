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
        Schema::table('department_objectives', function (Blueprint $table) {
            $table->string('source_type', 30)->default('manual')->after('kpi_name'); // 'manual', 'odoo_expense'
            $table->string('odoo_account_code', 50)->nullable()->after('source_type');
            $table->string('unit', 30)->nullable()->after('polarity'); // %, Rp Juta, Hari, dll
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('department_objectives', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'odoo_account_code', 'unit']);
        });
    }
};
