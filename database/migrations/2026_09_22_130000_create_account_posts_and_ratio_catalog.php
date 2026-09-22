<?php

use Database\Seeders\RatioCatalogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tingkat 2 — rasio keuangan dihitung dari pos akun (sheet L2).
 *
 * - account_balances : 16 pos akun per entitas per periode, diisi Finance
 *                      (sheet Asumsi bagian G).
 * - ratio_definitions: rasio mana yang dipakai tiap entitas beserta bobotnya.
 *                      Rumusnya ada di App\Support\Bsc\RatioLibrary.
 * - ratio_targets    : target tahunan tiap rasio per entitas.
 * - financial_ratios : ditambah kolom hasil mesin (kode, rubrik, bobot, skor
 *                      tertimbang, sumber). Kolom target/actual diperlebar:
 *                      decimal(10,2) tidak mampu menampung D1 (Rp 2,5 miliar)
 *                      dan memotong rasio seperti Cash Ratio 0,3049 menjadi 0,30.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('period', 7);
            $table->string('code', 10);
            // Aliran & HRIS: nilai YTD / rata-rata. Neraca: saldo akhir periode.
            $table->decimal('amount', 22, 2)->nullable();
            // Neraca saja: saldo awal tahun.
            $table->decimal('opening', 22, 2)->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'period', 'code']);
        });

        Schema::create('ratio_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code', 10);
            $table->decimal('weight', 6, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['entity_id', 'code']);
        });

        Schema::create('ratio_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('year', 4);
            $table->string('code', 10);
            $table->decimal('target', 22, 6)->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'year', 'code']);
        });

        Schema::table('financial_ratios', function (Blueprint $table) {
            $table->decimal('target', 22, 6)->default(0)->change();
            $table->decimal('actual', 22, 6)->default(0)->change();

            $table->string('ratio_code', 10)->nullable()->after('ratio_name');
            $table->string('unit', 10)->nullable()->after('ratio_code');
            $table->string('polarity', 10)->nullable()->after('unit');
            $table->decimal('weight', 6, 2)->nullable()->after('achievement_pct');
            $table->decimal('rubric_score', 6, 2)->nullable()->after('weight');
            $table->decimal('weighted_score', 8, 4)->nullable()->after('rubric_score');
            // manual = diisi langsung (data lama) · computed = dihitung dari pos akun.
            $table->string('source', 10)->default('manual')->after('status');
        });

        (new RatioCatalogSeeder)->run();
    }

    public function down(): void
    {
        // Rasio hasil hitungan milik fitur ini, dan nilainya (mis. D1 miliaran
        // rupiah) tidak muat di decimal(10,2).
        DB::table('financial_ratios')->where('source', 'computed')->delete();

        // Rasio manual di luar jangkauan decimal(10,2) dipotong agar penyempitan kolom
        // tidak gagal di MySQL mode strict (sama dengan rollback 140000).
        foreach (['target', 'actual'] as $kolom) {
            DB::table('financial_ratios')->where($kolom, '>', 99999999.99)->update([$kolom => 99999999.99]);
            DB::table('financial_ratios')->where($kolom, '<', -99999999.99)->update([$kolom => -99999999.99]);
        }

        Schema::table('financial_ratios', function (Blueprint $table) {
            $table->dropColumn(['ratio_code', 'unit', 'polarity', 'weight', 'rubric_score', 'weighted_score', 'source']);
            $table->decimal('target', 10, 2)->default(0)->change();
            $table->decimal('actual', 10, 2)->default(0)->change();
        });

        Schema::dropIfExists('ratio_targets');
        Schema::dropIfExists('ratio_definitions');
        Schema::dropIfExists('account_balances');
    }
};
