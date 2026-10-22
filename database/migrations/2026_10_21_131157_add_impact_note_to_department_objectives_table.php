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
            if (!Schema::hasColumn('department_objectives', 'impact_note')) {
                $table->text('impact_note')->nullable()->after('odoo_account_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('department_objectives', function (Blueprint $table) {
            $table->dropColumn('impact_note');
        });
    }
};
