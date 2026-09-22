<?php

namespace Tests\Feature;

use App\Livewire\BscDashboard;
use App\Livewire\HoldingConsolidation;
use App\Livewire\IndicatorTests;
use App\Livewire\KpiCascades;
use App\Livewire\RevenueTargets;
use App\Models\AccountBalance;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\IntercompanySale;
use App\Models\KpiCascade;
use App\Models\KpiTest;
use App\Models\Period;
use App\Models\RatioTarget;
use App\Models\RevenuePlan;
use App\Models\RevenueTarget;
use App\Models\User;
use App\Support\Bsc\Consolidation;
use App\Support\Bsc\RatioEngine;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndicatorAndConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private const POS_AKUN = [
        'PA01' => [540e9, null], 'PA02' => [351e9, null], 'PA03' => [135e9, null], 'PA04' => [81e9, null],
        'PA05' => [117e9, 108e9], 'PA06' => [99e9, 90e9], 'PA07' => [67.5e9, 63e9], 'PA08' => [58.5e9, 54e9],
        'PA09' => [333e9, 315e9], 'PA10' => [189e9, 180e9], 'PA11' => [756e9, 720e9], 'PA12' => [324e9, 315e9],
        'PA13' => [432e9, 405e9], 'PA14' => [270e9, 270e9], 'PA15' => [320, null], 'PA16' => [450000, null],
    ];

    private const TARGET = [
        'P1' => 37, 'P2' => 11, 'P3' => 12, 'P4' => 20,
        'A1' => 6, 'A2' => 1.2, 'A3' => 10, 'A4' => 60, 'A5' => 36, 'A6' => 45,
        'D1' => 2.8e9, 'D2' => 1.3e6, 'D3' => 7, 'D4' => 0.7,
        'L1' => 1.8, 'L2' => 1.2, 'L3' => 0.35,
        'S1' => 0.7, 'S2' => 0.4,
    ];

    private Entity $erdigma;

    private Entity $herbatech;

    protected function setUp(): void
    {
        parent::setUp();

        $this->erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        $this->herbatech = Entity::where('code', 'HERBATECH')->firstOrFail();
    }

    private function user(string $role, ?int $entityId): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Pengguna '.$role, 'email' => str($role)->slug().($entityId ?? 'holding').'@contoh.test',
            'password' => bcrypt('Herbatech#2026aman'), 'is_active' => true, 'entity_id' => $entityId,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function seedErdigmaFinance(string $period = '2026-08'): void
    {
        app(EntityContext::class)->runAs($this->erdigma->id, function () use ($period) {
            foreach (self::POS_AKUN as $kode => [$nilai, $awal]) {
                AccountBalance::create(['period' => $period, 'code' => $kode, 'amount' => $nilai, 'opening' => $awal]);
            }
            foreach (self::TARGET as $kode => $target) {
                RatioTarget::create(['year' => '2026', 'code' => $kode, 'target' => $target]);
            }
            app(RatioEngine::class)->materialize($period);
        });
    }

    private function adCostKpi(): KpiCascade
    {
        return app(EntityContext::class)->runAs($this->erdigma->id, fn () => KpiCascade::create([
            'year' => '2026', 'code' => 'TTC-H02', 'unit_code' => 'TTC', 'brand' => 'Eyebost', 'level' => 'Head',
            'position' => 'PGM TikTok Commerce — Eyebost', 'objective' => 'Ad cost ratio', 'measure_type' => 'Lag',
            'target' => 0.2, 'weight' => 100, 'kpi_type' => 'Driver', 'elasticity' => 0.5,
            'ratio_code' => 'P2', 'post_code' => 'PA03', 'direction' => 'Menurunkan', 'polarity' => 'Turun',
        ]));
    }

    /* ====================================================== UJI INDIKATOR */

    public function test_finance_runs_both_tests_and_approves_the_kpi(): void
    {
        $this->user('Admin FAT', $this->erdigma->id);
        $this->seedErdigmaFinance();
        $kpi = $this->adCostKpi();

        Livewire::test(IndicatorTests::class, ['year' => '2026'])
            ->call('select', $kpi->id)
            ->assertSet('coefficients.PA03', '-1') // titik awal dari arah KPI
            ->set('answers.1', 'ya')->set('answers.2', 'ya')->set('answers.5', 'ya')->set('answers.6', 'ya')
            ->set('period', '2026-08')
            ->set('improvement', '5')
            ->set('coefficients.PA01', '0.4')->set('coefficients.PA02', '0.4')->set('coefficients.PA03', '-0.3')
            ->assertViewHas('result', fn ($r) => $r['ujiA'] === 'LOLOS' && $r['ujiB']['result'] === 'LOLOS' && $r['recommended'] === 'Lolos')
            ->call('save')
            ->call('applyStatus', 'Lolos');

        $uji = KpiTest::where('kpi_cascade_id', $kpi->id)->firstOrFail();
        $this->assertSame('LOLOS', $uji->uji_a_result);
        $this->assertSame('LOLOS', $uji->uji_b_result);
        $this->assertTrue($uji->q3 && $uji->q4 && $uji->q7 && $uji->q8);
        $this->assertEqualsWithDelta(-0.3, $uji->uji_b_coefficients['PA03'], 1e-9);
        $this->assertSame(KpiCascade::LOLOS, $kpi->fresh()->validation_status);
        $this->assertStringContainsString('Uji B: LOLOS', $kpi->fresh()->finance_notes);
    }

    public function test_a_kpi_cannot_be_approved_before_it_is_tested(): void
    {
        $this->user('Admin FAT', $this->erdigma->id);
        $kpi = $this->adCostKpi();

        Livewire::test(IndicatorTests::class, ['year' => '2026'])
            ->call('select', $kpi->id)
            ->call('applyStatus', 'Lolos');

        $this->assertSame(KpiCascade::BELUM_DIUJI, $kpi->fresh()->validation_status);
    }

    public function test_only_finance_may_record_tests(): void
    {
        $this->user('Kepala Departemen', $this->erdigma->id);
        $kpi = $this->adCostKpi();

        Livewire::test(IndicatorTests::class, ['year' => '2026'])
            ->call('select', $kpi->id)
            ->set('answers.1', 'ya')
            ->call('save')
            ->call('applyStatus', 'Lolos');

        $this->assertSame(0, KpiTest::count());
        $this->assertSame(KpiCascade::BELUM_DIUJI, $kpi->fresh()->validation_status);
    }

    /* ================================================== FAKTOR REVISI */

    public function test_a_revenue_revision_adjusts_the_monitored_kpi_target(): void
    {
        $this->user('Admin FAT', $this->erdigma->id);
        $kpi = $this->adCostKpi();
        $kpi->update(['validation_status' => KpiCascade::LOLOS, 'target' => 100, 'elasticity' => 0.5]);
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);

        Livewire::test(RevenueTargets::class, ['year' => '2026'])
            ->set('approvedTarget', '900000000000')
            ->set('revisedTarget', '810000000000') // turun 10% → faktor 0,9
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(0.9, RevenuePlan::factorFor('2026'), 1e-12);

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->set('syncPeriod', '2026-08')
            ->call('syncToPeriod');

        // 100 × (1 + 0,5 × (0,9 − 1)) = 95.
        $this->assertEqualsWithDelta(95.0, (float) DepartmentObjective::where('kpi_code', 'TTC-H02')->value('target'), 1e-6);
    }

    /* ===================================================== KONSOLIDASI */

    public function test_group_revenue_eliminates_intercompany_sales(): void
    {
        $this->seedErdigmaFinance();
        $konteks = app(EntityContext::class);

        $konteks->runAs($this->herbatech->id, function () {
            RevenueTarget::create(['period' => '2026-01', 'target' => 400, 'actual' => 380]);
        });
        $konteks->runAs($this->erdigma->id, function () {
            RevenueTarget::create(['period' => '2026-01', 'target' => 600, 'actual' => 540]);
        });
        // Herbatech memasok Erdigma: rencana 100, realisasi 90 — keluar dari angka grup.
        IntercompanySale::create(['period' => '2026-01', 'seller_entity_id' => $this->herbatech->id, 'buyer_entity_id' => $this->erdigma->id, 'planned_amount' => 100, 'actual_amount' => 90]);

        $hasil = app(Consolidation::class)->forPeriod('2026-08');
        $per = collect($hasil['entities'])->keyBy(fn ($b) => $b['entity']->code);
        $g = $hasil['group'];

        $this->assertSame(95.0, $per['HERBATECH']['f1']);
        $this->assertSame(90.0, $per['ERDIGMA']['f1']);
        $this->assertSame(94.1, $per['ERDIGMA']['f2']);
        $this->assertNull($per['AEJ']['apex']);

        $this->assertEqualsWithDelta(900.0, $g['revenue_target_net'], 1e-9);
        $this->assertEqualsWithDelta(830.0, $g['revenue_actual_net'], 1e-9);
        $this->assertSame(round(830 / 900 * 100, 2), $g['f1']);
        // Hanya Erdigma punya F2 → F2 grup = 94,1.
        $this->assertSame(94.1, $g['f2']);
        $this->assertSame(round(0.45 * round(830 / 900 * 100, 2) + 0.55 * 94.1, 2), $g['apex']);
    }

    public function test_consolidation_restores_the_entity_context(): void
    {
        $konteks = app(EntityContext::class);
        $konteks->use($this->herbatech->id);

        app(Consolidation::class)->forPeriod('2026-08');

        $this->assertSame($this->herbatech->id, $konteks->id());
    }

    public function test_the_entity_score_matches_its_own_dashboard(): void
    {
        $this->user('Super Admin', null);
        $this->seedErdigmaFinance();
        app(EntityContext::class)->runAs($this->erdigma->id, function () {
            Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
            RevenueTarget::create(['period' => '2026-08', 'target' => 100, 'actual' => 90]);
        });

        $dashboard = app(EntityContext::class)->runAs($this->erdigma->id,
            fn () => Livewire::test(BscDashboard::class, ['selectedPeriod' => '2026-08'])->viewData('apexScore'));
        $konsolidasi = collect(app(Consolidation::class)->forPeriod('2026-08')['entities'])
            ->firstWhere(fn ($b) => $b['entity']->code === 'ERDIGMA')['apex'];

        $this->assertSame($dashboard, $konsolidasi);
        $this->assertSame(round(0.45 * 90 + 0.55 * 94.1, 2), $konsolidasi);
    }

    public function test_holding_users_can_record_eliminations(): void
    {
        $this->user('Super Admin', null);

        Livewire::test(HoldingConsolidation::class, ['period' => '2026-03'])
            ->call('openCreate')
            ->set('form.period', '2026-03')
            ->set('form.seller_entity_id', (string) $this->herbatech->id)
            ->set('form.buyer_entity_id', (string) $this->erdigma->id)
            ->set('form.actual_amount', '2500000000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, IntercompanySale::count());

        // Penjual = pembeli ditolak.
        Livewire::test(HoldingConsolidation::class, ['period' => '2026-03'])
            ->call('openCreate')
            ->set('form.seller_entity_id', (string) $this->erdigma->id)
            ->set('form.buyer_entity_id', (string) $this->erdigma->id)
            ->set('form.actual_amount', '1')
            ->call('save')
            ->assertHasErrors('form.buyer_entity_id');
        $this->assertSame(1, IntercompanySale::count());
    }

    public function test_entity_bound_users_cannot_see_the_consolidation(): void
    {
        // Admin FAT punya izinnya, tetapi terikat satu entitas → data entitas lain tertutup.
        $this->user('Admin FAT', $this->erdigma->id);

        $this->get(route('consolidation'))->assertForbidden();
    }

    public function test_roles_without_the_permission_cannot_open_it(): void
    {
        $this->user('Viewer', null);

        $this->get(route('consolidation'))->assertForbidden();
    }
}
