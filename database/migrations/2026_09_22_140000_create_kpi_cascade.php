<?php

use Database\Seeders\AccountPostRoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tingkat 3 — cascade KPI (sheet "Peta Rasio-Akun-Dept" & "L3 Cascade KPI").
 *
 * - account_post_roles : Peta bagian 2 — unit kerja mana Pemilik (O) atau
 *                        Kontributor (K) tiap pos akun, per entitas.
 * - kpi_cascades       : tabel cascade KPI tahunan Head → Supervisor → Staff.
 * - department_objectives: ditautkan ke KPI cascade asalnya. Kolom target/actual
 *                        diperlebar: decimal(10,2) tidak mampu menampung target
 *                        revenue channel (ratusan miliar) dan memotong target
 *                        pecahan seperti 0,97.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_post_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('unit_code', 30);
            $table->string('post_code', 10);
            $table->char('role', 1); // O = Pemilik, K = Kontributor
            $table->timestamps();

            $table->unique(['entity_id', 'unit_code', 'post_code']);
        });

        Schema::create('kpi_cascades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('year', 4);
            $table->string('code', 50);
            $table->string('unit_code', 30);
            $table->string('brand', 100)->nullable();
            $table->string('level', 20);            // Head, Supervisor, Staff
            $table->string('parent_code', 50)->nullable();
            $table->string('position', 150);        // Jabatan / PIC
            $table->string('objective', 255);       // Sasaran kerja
            $table->string('measure_type', 20);     // Lag, Lead, Output
            $table->decimal('target', 22, 6)->nullable();
            $table->string('unit_label', 50)->nullable(); // satuan
            $table->string('polarity', 10)->default('Naik');
            $table->string('reporting_period', 100)->nullable();
            $table->text('method')->nullable();
            $table->text('key_initiative')->nullable();
            $table->text('work_program')->nullable();
            $table->text('record')->nullable();
            $table->decimal('weight', 6, 2)->default(0); // persen
            $table->string('kpi_type', 30)->default('Driver'); // Driver, Guardrail
            $table->decimal('elasticity', 6, 3)->nullable();
            $table->string('ratio_code', 10)->nullable(); // REV atau P1…S2
            $table->string('post_code', 10)->nullable();
            $table->string('direction', 20)->nullable(); // Menaikkan, Menurunkan
            $table->string('individual_type', 20)->nullable(); // Rutin, Milestone (Staff)
            $table->string('validation_status', 20)->default('Belum diuji');
            $table->text('finance_notes')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['entity_id', 'year', 'code']);
            $table->index(['entity_id', 'year', 'unit_code']);
        });

        Schema::table('department_objectives', function (Blueprint $table) {
            $table->decimal('target', 22, 6)->default(0)->change();
            $table->decimal('actual', 22, 6)->default(0)->change();
            $table->foreignId('kpi_cascade_id')->nullable()->after('entity_id')
                ->constrained('kpi_cascades')->nullOnDelete();
        });

        (new AccountPostRoleSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('department_objectives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kpi_cascade_id');
        });

        // Nilai yang lebih besar dari decimal(10,2) dibatasi agar kolom dapat
        // dikembalikan tanpa galat di MySQL mode ketat.
        foreach (['target', 'actual'] as $kolom) {
            DB::table('department_objectives')
                ->where($kolom, '>', 99999999.99)->update([$kolom => 99999999.99]);
        }

        Schema::table('department_objectives', function (Blueprint $table) {
            $table->decimal('target', 10, 2)->default(0)->change();
            $table->decimal('actual', 10, 2)->default(0)->change();
        });

        Schema::dropIfExists('kpi_cascades');
        Schema::dropIfExists('account_post_roles');
    }
};
