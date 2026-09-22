<?php

use Database\Seeders\EntityStructureSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-entitas: satu aplikasi untuk empat entitas di bawah holding.
 *
 * 1. Tabel entities, work_units (unit kerja per entitas), revenue_targets (L1).
 * 2. Kolom entity_id pada seluruh data BSC dan pada users (kosong = pengguna
 *    level holding yang dapat berpindah antarentitas).
 * 3. Periode menjadi unik per entitas — tiap entitas membuka, mengunci, dan
 *    menilai periodenya sendiri.
 * 4. Data yang sudah ada dipindahkan ke entitas instalasi (BSC_DEFAULT_ENTITY di
 *    .env; bila kodenya tidak dikenal, HERBATECH — aplikasi ini semula dibangun
 *    untuk Herbatech), supaya tetap tampil pada instalasi satu entitas. Kode departemen yang dipakai data tetapi tidak ada di
 *    katalog dibuatkan unit kerjanya, agar tidak ada data yatim.
 */
return new class extends Migration
{
    /** Tabel data BSC yang menjadi milik satu entitas. */
    private const TABEL_BERENTITAS = [
        'periods',
        'financial_ratios',
        'department_objectives',
        'action_plans',
        'staging_logs',
    ];

    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('legal_name', 200);
            $table->string('industry', 30)->default('manufaktur');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('work_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('stream', 100)->nullable();
            $table->string('reports_to', 100)->nullable();
            $table->text('scope')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['entity_id', 'code']);
        });

        Schema::create('revenue_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('target', 20, 2)->default(0);
            $table->decimal('actual', 20, 2)->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'period']);
        });

        foreach (self::TABEL_BERENTITAS as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->foreignId('entity_id')->nullable()->after('id')
                    ->constrained('entities')->cascadeOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('entity_id')->nullable()->after('dept_code')
                ->constrained('entities')->nullOnDelete();
        });

        // Entitas & unit kerja bawaan. Seeder yang sama dipakai db:seed,
        // sehingga keduanya tidak mungkin berbeda.
        (new EntityStructureSeeder)->run();

        $pemilik = DB::table('entities')->where('code', strtoupper((string) config('bsc.default_entity')))->value('id')
            ?? DB::table('entities')->where('code', 'HERBATECH')->value('id');

        foreach (self::TABEL_BERENTITAS as $tabel) {
            DB::table($tabel)->whereNull('entity_id')->update(['entity_id' => $pemilik]);
        }

        // Kode departemen yang sudah dipakai data tetapi belum ada di katalog.
        $dipakai = DB::table('department_objectives')->where('entity_id', $pemilik)->pluck('dept_code')
            ->merge(DB::table('action_plans')->where('entity_id', $pemilik)->pluck('owner_dept'))
            ->filter()
            ->map(fn ($kode) => strtoupper(trim($kode)))
            ->unique();

        $sudahAda = DB::table('work_units')->where('entity_id', $pemilik)->pluck('code');
        $urutan = (int) DB::table('work_units')->where('entity_id', $pemilik)->max('sort');

        foreach ($dipakai->diff($sudahAda) as $kode) {
            DB::table('work_units')->insert([
                'entity_id' => $pemilik,
                'code' => $kode,
                'name' => $kode,
                'stream' => null,
                'is_active' => true,
                'sort' => ++$urutan,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Periode unik per entitas, bukan unik secara global.
        Schema::table('periods', function (Blueprint $table) {
            $table->dropUnique(['period']);
            $table->unique(['entity_id', 'period']);
        });
    }

    public function down(): void
    {
        // Di MySQL, indeks unik (entity_id, period) sekaligus menjadi indeks
        // foreign key entity_id, jadi foreign key harus dilepas lebih dulu.
        Schema::table('periods', function (Blueprint $table) {
            $table->dropForeign(['entity_id']);
            $table->dropUnique(['entity_id', 'period']);
            $table->dropColumn('entity_id');
        });

        // Tanpa entitas, periode kembali unik secara global: bila beberapa
        // entitas memiliki periode yang sama, hanya baris tertua yang dipertahankan.
        $pertama = DB::table('periods')->selectRaw('MIN(id) as id')->groupBy('period')->pluck('id');
        DB::table('periods')->whereNotIn('id', $pertama)->delete();

        Schema::table('periods', function (Blueprint $table) {
            $table->unique(['period']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entity_id');
        });

        foreach (array_diff(self::TABEL_BERENTITAS, ['periods']) as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropConstrainedForeignId('entity_id');
            });
        }

        Schema::dropIfExists('revenue_targets');
        Schema::dropIfExists('work_units');
        Schema::dropIfExists('entities');
    }
};
