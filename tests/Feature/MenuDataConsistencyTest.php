<?php

namespace Tests\Feature;

use App\Livewire\ActionPlans;
use App\Livewire\BscDashboard;
use App\Livewire\DepartmentObjectives;
use App\Livewire\FinancialRatios;
use App\Livewire\StagingLogs;
use App\Livewire\SystemIntegration;
use App\Models\AccountBalance;
use App\Models\ActionPlan;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\KpiCascade;
use App\Models\Period;
use App\Models\StagingLog;
use App\Models\User;
use App\Support\Bsc\RatioEngine;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Menu-menu lama tidak lagi memakai periode, departemen, atau rumus yang
 * ditulis mati — semuanya mengikuti data entitas aktif.
 */
class MenuDataConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private Entity $erdigma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class); // contoh Erdigma periode 2026-08, lewat cascade
        $this->erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        $admin = User::create([
            'name' => 'Admin Erdigma', 'email' => 'admin-erdigma@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => $this->erdigma->id,
        ]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
        app(EntityContext::class)->use($this->erdigma->id);
    }

    /* ------------------------------------------------------ periode bawaan */

    public function test_pages_open_on_the_latest_period(): void
    {
        Period::create(['period' => '2026-09', 'status' => 'OPEN', 'apex_score' => 0]);

        Livewire::test(BscDashboard::class)->assertSet('selectedPeriod', '2026-09');
        Livewire::test(FinancialRatios::class)->assertSet('selectedPeriod', '2026-09');
        Livewire::test(DepartmentObjectives::class)->assertSet('selectedPeriod', '2026-09');
    }

    /* ---------------------------------------------------- periode baru */

    public function test_a_new_period_copies_the_latest_period_and_keeps_cascade_links(): void
    {
        Livewire::test(BscDashboard::class)
            ->set('newPeriodInput', '2026-09')
            ->call('createNewPeriod');

        $baru = DepartmentObjective::where('period', '2026-09')->get();
        $this->assertCount(8, $baru);
        $this->assertTrue($baru->every(fn ($o) => $o->kpi_cascade_id !== null), 'Sasaran periode baru harus tetap tertaut ke cascade.');
        $this->assertTrue($baru->every(fn ($o) => (float) $o->actual === 0.0));
        // Rasio hasil hitungan tidak disalin — periode baru mendapatkannya dari pos akunnya sendiri.
        $this->assertSame(0, FinancialRatio::where('period', '2026-09')->count());
    }

    public function test_a_new_year_takes_its_objectives_from_that_years_approved_kpis(): void
    {
        KpiCascade::create([
            'year' => '2027', 'code' => 'SCM-H01', 'unit_code' => 'SCM', 'level' => 'Head', 'position' => 'Kepala SCM',
            'objective' => 'Service level 2027', 'measure_type' => 'Lag', 'target' => 95, 'weight' => 100,
            'kpi_type' => 'Driver', 'ratio_code' => 'REV', 'post_code' => 'PA01', 'validation_status' => KpiCascade::LOLOS,
        ]);

        Livewire::test(BscDashboard::class)
            ->set('newPeriodInput', '2027-01')
            ->call('createNewPeriod');

        // KPI cascade 2026 tidak dibawa ke 2027; yang masuk adalah KPI Lolos 2027.
        $this->assertSame(['SCM-H01'], DepartmentObjective::where('period', '2027-01')->pluck('kpi_code')->all());
    }

    /* ---------------------------------------------------- program kerja */

    public function test_action_plans_only_offer_the_latest_periods_objectives(): void
    {
        Period::create(['period' => '2026-09', 'status' => 'OPEN', 'apex_score' => 0]);
        DepartmentObjective::create(['period' => '2026-09', 'dept_code' => 'SCM', 'kpi_code' => 'SCM-01', 'kpi_name' => 'Service level', 'target' => 90, 'actual' => 50, 'status' => 'Di Bawah Target']);

        Livewire::test(ActionPlans::class)
            ->assertViewHas('offTargetObjectives', fn ($o) => $o->pluck('period')->unique()->all() === ['2026-09']);
    }

    public function test_an_action_plan_cannot_point_at_another_entitys_objective(): void
    {
        $asing = app(EntityContext::class)->runAs(Entity::where('code', 'AEJ')->value('id'), fn () => DepartmentObjective::create([
            'period' => '2026-08', 'dept_code' => 'OPS', 'kpi_code' => 'OPS-99', 'kpi_name' => 'x', 'target' => 1, 'actual' => 0,
        ]));

        $sebelum = ActionPlan::count();

        Livewire::test(ActionPlans::class)
            ->set('title', 'Program kerja uji')
            ->set('ownerDept', 'SCM')
            ->set('objectiveId', $asing->id)
            ->call('createPlan')
            ->assertHasErrors('objectiveId');

        $this->assertSame($sebelum, ActionPlan::count());
    }

    /* ------------------------------------------------------ staging log */

    public function test_the_audit_log_can_be_narrowed_down_by_its_filters(): void
    {
        $buat = fn (string $unit, string $status, string $pesan) => StagingLog::create([
            'period' => '2026-08', 'dept_code' => $unit, 'idempotency_key' => 'IDEMP-'.$unit.'-'.$status,
            'status' => $status, 'source_version' => 1, 'message' => $pesan,
        ]);

        $buat('TTC', 'SCORED', 'Odoo: 3 pos akun uji diperbarui.');
        $buat('FIN', 'ERROR', 'Unggahan CSV ditolak: kode akun uji belum dipetakan.');

        $semua = StagingLog::count();

        // Saringan unit kerja memakai daftar unit entitas ini.
        Livewire::test(StagingLogs::class)
            ->assertViewHas('units', fn ($u) => $u->pluck('code')->contains('TTC'))
            ->assertViewHas('logs', fn ($l) => $l->total() === $semua)
            ->set('status', 'ERROR')
            ->assertViewHas('logs', fn ($l) => $l->total() === 1 && $l->first()->dept_code === 'FIN')
            ->set('status', '')
            ->set('unit', 'TTC')
            ->assertViewHas('logs', fn ($l) => $l->total() === 1 && $l->first()->dept_code === 'TTC')
            ->set('unit', '')
            ->set('cari', 'pos akun uji')
            ->assertViewHas('logs', fn ($l) => $l->total() === 1 && $l->first()->dept_code === 'TTC')
            ->call('bersihkanSaringan')
            ->assertViewHas('logs', fn ($l) => $l->total() === $semua);
    }

    /* ------------------------------------------------------- integrasi */

    public function test_finance_payload_goes_through_the_account_posts(): void
    {
        Livewire::test(SystemIntegration::class)
            ->assertSet('financePeriod', '2026-08')
            ->set('salesPayload', 540000)
            ->set('hppPayload', 351000)
            ->call('processFinancePayload')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(540e9, AccountBalance::where('period', '2026-08')->where('code', 'PA01')->value('amount'), 1);
        $this->assertEqualsWithDelta(351e9, AccountBalance::where('period', '2026-08')->where('code', 'PA02')->value('amount'), 1);
        // Rasio dihitung mesin 19 rasio (source computed), bukan nama rasio versi lama.
        $this->assertTrue(FinancialRatio::where('period', '2026-08')->where('source', RatioEngine::SOURCE_COMPUTED)->where('ratio_code', 'P1')->exists());
        $this->assertFalse(FinancialRatio::where('ratio_name', 'Current Ratio (CR)')->exists());
    }

    public function test_manual_payload_respects_polarity_and_the_cascade_target(): void
    {
        $komponen = Livewire::test(SystemIntegration::class)
            ->assertSet('deptPayload', 'BMK')
            ->set('deptPayload', 'SCM')
            ->set('kpiCodePayload', 'SCM-02'); // Turun, target 5 dari cascade

        $komponen->set('targetPayload', 99)->set('actualPayload', 6.25)->call('processManualPayload')->assertHasNoErrors();

        $o = DepartmentObjective::where('period', '2026-08')->where('kpi_code', 'SCM-02')->first();
        $this->assertSame(5.0, (float) $o->target);             // target cascade tidak ditimpa
        $this->assertSame(80.0, (float) $o->achievement_pct);   // Turun: 5 ÷ 6,25
        $this->assertSame('Waspada', $o->status);

        $komponen->set('kpiCodePayload', 'KPI-PROD-001')->call('processManualPayload')->assertHasErrors('kpiCodePayload');
    }
}
