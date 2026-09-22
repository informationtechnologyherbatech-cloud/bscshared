<?php

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\KpiCascade;
use App\Models\KpiTest;
use App\Models\Period;
use App\Models\RevenueForecastPlan;
use App\Models\RevenueTarget;
use App\Models\StagingLog;
use App\Models\User;
use App\Models\WorkUnit;
use App\Support\Bsc\RatioEngine;
use App\Support\EntityContext;
use Database\Seeders\BscDataSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * BSC_DEFAULT_ENTITY menentukan entitas yang dibuka pertama dan pemilik data
 * contoh saat db:seed; departemen yang tampil adalah milik entitas itu.
 */
class DefaultEntitySeedingTest extends TestCase
{
    use RefreshDatabase;

    private function entity(string $kode): Entity
    {
        return Entity::where('code', $kode)->firstOrFail();
    }

    /** Hitung tanpa pembatas entitas, per kode entitas. */
    private function perEntity(string $model): array
    {
        return $model::withoutGlobalScopes()->get()
            ->groupBy(fn ($r) => $r->entity_id === null ? 'KOSONG' : Entity::find($r->entity_id)->code)
            ->map->count()->all();
    }

    public function test_seeding_belongs_to_erdigma_by_default(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['ERDIGMA' => 1], $this->perEntity(Period::class));
        $this->assertSame(['ERDIGMA' => 19], $this->perEntity(FinancialRatio::class)); // 19 rasio hasil hitungan pos akun
        $this->assertSame(['ERDIGMA' => 8], $this->perEntity(DepartmentObjective::class));
        $this->assertSame(['ERDIGMA' => 2], $this->perEntity(StagingLog::class));
    }

    public function test_erdigma_sample_objectives_use_erdigma_units(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(EntityContext::class)->use($this->entity('ERDIGMA')->id);

        $unit = WorkUnit::pluck('code');
        $dipakai = DepartmentObjective::distinct()->pluck('dept_code');

        // Sasaran mutu contoh: OPS → SCM, MKT → BMK; log staging: PROD → MFG, HRD → HRG.
        $this->assertEqualsCanonicalizing(['SCM', 'BMK'], $dipakai->all());
        $this->assertEqualsCanonicalizing(['MFG', 'HRG'], StagingLog::distinct()->pluck('dept_code')->all());
        $this->assertTrue($dipakai->every(fn ($k) => $unit->contains($k)), 'Setiap departemen contoh harus unit kerja Erdigma.');
        $this->assertTrue(DepartmentObjective::where('kpi_code', 'SCM-02')->exists());
        $this->assertSame(17, $unit->count()); // tidak ada unit asing yang ditambahkan
    }

    public function test_another_entity_can_be_chosen_through_the_env(): void
    {
        config(['bsc.default_entity' => 'HERBATECH']);

        $this->seed(DatabaseSeeder::class);
        app(EntityContext::class)->use($this->entity('HERBATECH')->id);

        $this->assertSame(['HERBATECH' => 8], $this->perEntity(DepartmentObjective::class));
        $this->assertEqualsCanonicalizing(['OPS', 'MKT'], DepartmentObjective::distinct()->pluck('dept_code')->all());
        $this->assertEqualsCanonicalizing(['PRO', 'HRD'], StagingLog::distinct()->pluck('dept_code')->all());
        // MKT tidak ada di katalog manufaktur → dibuat agar sasaran contohnya punya unit.
        $this->assertSame('Marketing & Penjualan', WorkUnit::where('code', 'MKT')->value('name'));
    }

    public function test_an_unknown_entity_code_fails_loudly(): void
    {
        config(['bsc.default_entity' => 'SALAH']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BSC_DEFAULT_ENTITY');

        $this->seed(BscDataSeeder::class);
    }

    public function test_holding_users_land_on_the_default_entity(): void
    {
        $holding = User::create(['name' => 'Holding', 'email' => 'holding@contoh.test', 'password' => bcrypt('x'), 'is_active' => true]);
        // Entitas lain punya data paling baru — tetap mendarat di entitas bawaan.
        app(EntityContext::class)->runAs($this->entity('AEJ')->id, fn () => Period::create(['period' => '2026-09', 'status' => 'OPEN', 'apex_score' => 0]));

        $this->actingAs($holding);
        $this->assertSame($this->entity('ERDIGMA')->id, app(EntityContext::class)->id());

        config(['bsc.default_entity' => 'AEJ']);
        app(EntityContext::class)->forget();
        $this->assertSame($this->entity('AEJ')->id, app(EntityContext::class)->id());
    }

    public function test_an_unknown_default_falls_back_to_the_most_recent_data(): void
    {
        config(['bsc.default_entity' => 'SALAH']);
        $holding = User::create(['name' => 'Holding', 'email' => 'holding@contoh.test', 'password' => bcrypt('x'), 'is_active' => true]);
        app(EntityContext::class)->runAs($this->entity('AEJ')->id, fn () => Period::create(['period' => '2026-09', 'status' => 'OPEN', 'apex_score' => 0]));

        $this->actingAs($holding);
        $this->assertSame($this->entity('AEJ')->id, app(EntityContext::class)->id());
    }

    public function test_entity_bound_users_ignore_the_default(): void
    {
        $user = User::create([
            'name' => 'Staf Herbatech', 'email' => 'staf@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => $this->entity('HERBATECH')->id,
        ]);

        $this->actingAs($user);
        $this->assertSame($this->entity('HERBATECH')->id, app(EntityContext::class)->id());
    }

    public function test_the_demo_fills_all_four_pyramid_tiers(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(EntityContext::class)->use($this->entity('ERDIGMA')->id);

        // T1: target 840 M difasing 70 M/bulan, realisasi Jan–Agu workbook → 540 ÷ 560.
        $this->assertSame(96.43, RevenueTarget::cumulativeAchievement('2026-08'));
        $this->assertNotNull(RevenueForecastPlan::where('year', '2027')->first());
        // T2: F2 94,1.
        $this->assertSame(94.1, RatioEngine::storedScore('2026-08'));
        // T3: 8 KPI cascade, semuanya sudah diuji & lolos.
        $this->assertSame(8, KpiTest::count());
        $this->assertSame(8, KpiTest::whereIn('uji_a_result', ['LOLOS', 'LOLOS (guardrail)'])->count());
        $this->assertSame(6, KpiTest::where('uji_b_result', 'LOLOS')->count()); // 6 Driver
        // T4: program kerja untuk sasaran yang Waspada.
        $this->assertSame(3, ActionPlan::count());
        $this->assertEqualsCanonicalizing(['SCM-01', 'SCM-03', 'BMK-02'], ActionPlan::with('objective')->get()->pluck('objective.kpi_code')->all());
    }

    public function test_the_demo_seeder_can_be_run_again_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(BscDataSeeder::class);
        app(EntityContext::class)->use($this->entity('ERDIGMA')->id);

        $this->assertSame(3, ActionPlan::count());
        $this->assertSame(8, KpiCascade::count());
        $this->assertSame(12, RevenueTarget::where('period', 'like', '2026-%')->count());
    }
}
