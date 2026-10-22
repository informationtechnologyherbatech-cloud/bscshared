<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('department_objectives', function (Blueprint $table) {
            if (!Schema::hasColumn('department_objectives', 'pos_anggaran')) {
                $table->string('pos_anggaran', 255)->nullable()->after('odoo_account_code');
            }
        });

        // Migrasikan kode PA yang sempat tersimpan di odoo_account_code ke pos_anggaran
        if (Schema::hasColumn('department_objectives', 'pos_anggaran')) {
            DB::table('department_objectives')
                ->where('odoo_account_code', 'LIKE', '%PA%')
                ->update([
                    'pos_anggaran' => DB::raw('odoo_account_code'),
                    'odoo_account_code' => null,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('department_objectives', function (Blueprint $table) {
            $table->dropColumn('pos_anggaran');
        });
    }
};
