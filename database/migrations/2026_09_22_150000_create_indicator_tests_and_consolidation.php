<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tingkat 4 & konsolidasi holding.
 *
 * - kpi_tests          : sheet "L4 Uji Indikator" — Uji A (8 pertanyaan) dan
 *                        Uji B (simulasi koefisien transmisi) per KPI cascade.
 * - revenue_plans      : target revenue tahunan disahkan & revisi (Asumsi A,
 *                        B7–B9) — sumber faktor revisi untuk "Target disesuaikan".
 * - intercompany_sales : penjualan antarentitas grup per bulan, dieliminasi
 *                        dari revenue konsolidasi holding.
 * - izin "view consolidation" & "manage consolidation".
 */
return new class extends Migration
{
    private const IZIN = [
        'view consolidation' => ['Super Admin', 'Admin FAT'],
        'manage consolidation' => ['Super Admin', 'Admin FAT'],
    ];

    public function up(): void
    {
        Schema::create('kpi_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('kpi_cascade_id')->unique()->constrained('kpi_cascades')->cascadeOnDelete();
            // Uji A: jawaban Q1–Q8 (true = Ya). Q3, Q4, Q7, Q8 dihitung dari data
            // saat disimpan; sisanya dijawab Keuangan.
            foreach (range(1, 8) as $q) {
                $table->boolean('q'.$q)->nullable();
            }
            $table->string('uji_a_result', 30)->nullable();
            // Uji B: periode acuan, % perbaikan KPI, koefisien & keterangan per pos akun.
            $table->string('uji_b_period', 7)->nullable();
            $table->decimal('uji_b_improvement', 10, 6)->nullable();
            $table->json('uji_b_coefficients')->nullable();
            $table->json('uji_b_notes')->nullable();
            $table->string('uji_b_result', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('revenue_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('year', 4);
            $table->decimal('approved_target', 22, 2);
            $table->decimal('revised_target', 22, 2)->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'year']);
        });

        Schema::create('intercompany_sales', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);
            $table->foreignId('seller_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('buyer_entity_id')->constrained('entities')->cascadeOnDelete();
            // Rencana (ikut dieliminasi dari target grup) & realisasi (dari revenue grup).
            $table->decimal('planned_amount', 22, 2)->nullable();
            $table->decimal('actual_amount', 22, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            // Nama eksplisit: nama bawaan melebihi batas 64 karakter MySQL.
            $table->unique(['period', 'seller_entity_id', 'buyer_entity_id'], 'ic_sales_period_pair_unique');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::IZIN as $nama => $peran) {
            $izin = Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);

            foreach ($peran as $namaPeran) {
                $role = Role::where('name', $namaPeran)->where('guard_name', 'web')->first();

                if ($role && ! $role->hasPermissionTo($izin)) {
                    $role->givePermissionTo($izin);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', array_keys(self::IZIN))->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('intercompany_sales');
        Schema::dropIfExists('revenue_plans');
        Schema::dropIfExists('kpi_tests');
    }
};
