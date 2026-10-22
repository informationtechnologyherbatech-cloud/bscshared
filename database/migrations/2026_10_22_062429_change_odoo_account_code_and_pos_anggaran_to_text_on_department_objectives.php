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
            $table->text('odoo_account_code')->nullable()->change();
            $table->text('pos_anggaran')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('department_objectives', function (Blueprint $table) {
            $table->string('odoo_account_code', 255)->nullable()->change();
            $table->string('pos_anggaran', 255)->nullable()->change();
        });
    }
};
