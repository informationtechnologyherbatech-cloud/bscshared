<?php

namespace Tests\Feature;

use App\Livewire\AppSettings;
use App\Livewire\DepartmentObjectives;
use App\Livewire\FinancialRatios;
use App\Livewire\ManageUsers;
use App\Livewire\RevenuePlanning;
use App\Livewire\RevenueTargets;
use App\Livewire\SystemIntegration;
use App\Models\AccountBalance;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\RevenueForecastPlan;
use App\Models\RevenuePlan;
use App\Models\RevenueTarget;
use App\Models\User;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\Consolidation;
use App\Support\Bsc\IndicatorTest;
use App\Support\Bsc\MonitoringSync;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use App\Support\Bsc\RevenueForecast;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi temuan review branch multi-entitas: perhitungan, kunci periode CLOSED,
 * dan batas hak akses.
 */
class ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private Entity $erdigma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class); // contoh Erdigma 2026-08
        $this->erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        app(EntityContext::class)->use($this->erdigma->id);
    }

    private function actingAsRole(string $role, ?int $entityId = null): User
    {
        $user = User::create([
            'name' => $role, 'email' => str_replace(' ', '', strtolower($role)).'-'.uniqid().'@contoh.test',
            'password' => bcrypt('x'), 'is_active' => true, 'entity_id' => $entityId ?? $this->erdigma->id,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /* ------------------------------------------------------------ perhitungan */

    public function test_f1_lists_months_whose_actual_is_still_empty(): void
    {
        RevenueTarget::query()->delete();
        RevenueTarget::create(['period' => '2026-07', 'target' => 100, 'actual' => 100]);
        RevenueTarget::create(['period' => '2026-08', 'target' => 100, 'actual' => null]);

        $f1 = RevenueTarget::cumulative('2026-08');

        $this->assertSame(50.0, $f1['score']); // rumus workbook tetap
        $this->assertSame(['2026-08'], $f1['months_missing']);
    }

    public function test_group_f1_is_empty_rather_than_zero_without_any_actual(): void
    {
        RevenueTarget::query()->update(['actual' => null]);

        $grup = app(Consolidation::class)->forPeriod('2026-08')['group'];

        $this->assertNull($grup['f1']);
    }

    public function test_an_unreported_decreasing_kpi_is_not_marked_achieved(): void
    {
        $obj = DepartmentObjective::where('period', '2026-08')->where('polarity', 'Turun')->whereNotNull('kpi_cascade_id')->firstOrFail();
        $obj->update(['actual' => 0, 'achievement_pct' => 0, 'status' => 'Di Bawah Target']);

        MonitoringSync::syncPeriod('2026-08');

        $this->assertSame('Di Bawah Target', $obj->fresh()->status);
        $this->assertEquals(0, $obj->fresh()->achievement_pct);
    }

    public function test_zero_and_negative_targets_follow_the_polarity(): void
    {
        $this->assertSame(100.0, RatioLibrary::objectiveAchievement(0, 0, 'Turun'));
        $this->assertSame(0.0, RatioLibrary::objectiveAchievement(3, 0, 'Turun'));
        $this->assertSame(50.0, RatioLibrary::achievement(-3, -2, 'Naik'));   // rugi lebih dalam
        $this->assertSame(100.0, RatioLibrary::achievement(-1, -2, 'Naik'));  // lebih baik dari target
        $this->assertSame(50.0, RatioLibrary::achievement(2, 1, 'Turun'));    // DER 2 vs target 1
    }

    public function test_editing_a_manual_ratio_respects_its_polarity(): void
    {
        $this->actingAsRole('Super Admin');
        $rasio = FinancialRatio::create([
            'period' => '2026-08', 'category' => 'Solvabilitas', 'ratio_name' => 'DER manual',
            'polarity' => 'Turun', 'target' => 1, 'actual' => 1, 'achievement_pct' => 100, 'status' => 'Tercapai',
        ]);

        Livewire::test(FinancialRatios::class)
            ->call('editRatio', $rasio->id)
            ->set('editTarget', 1)->set('editActual', 2)
            ->call('updateRatio');

        $this->assertEquals(50, $rasio->fresh()->achievement_pct);
        $this->assertSame('Di Bawah Target', $rasio->fresh()->status);
    }

    public function test_seasonal_phasing_never_produces_negative_months_and_sums_exactly(): void
    {
        $indeks = RevenueForecast::seasonalIndex(['01' => 100, '02' => 100, '03' => 100, '04' => 100], 300);
        $fasing = RevenueForecast::phase(1000000.03, $indeks);

        $this->assertGreaterThan(0, min($fasing));
        $this->assertEqualsWithDelta(1000000.03, array_sum($fasing), 0.001);
    }

    public function test_uji_b_judges_a_range_ratio_automatically(): void
    {
        $mesin = app(RatioEngine::class);
        $dipakai = AccountPosts::usedValues($mesin->inputs('2026-08'), 8);

        $hasil = IndicatorTest::simulate($dipakai, 0.05, ['PA07' => 0.5], 'A6', $mesin->targetsFor('2026'));

        $this->assertIsBool($hasil['favourable']); // dulu selalu null → selalu REVISI
    }

    /* ------------------------------------------------------ kunci periode CLOSED */

    public function test_a_closed_objective_cannot_be_edited_from_an_open_period(): void
    {
        $this->actingAsRole('Super Admin');
        Period::create(['period' => '2026-09', 'status' => 'OPEN', 'apex_score' => 0]);
        Period::where('period', '2026-08')->update(['status' => 'CLOSED']);
        $tertutup = DepartmentObjective::where('period', '2026-08')->firstOrFail();

        Livewire::test(DepartmentObjectives::class)
            ->set('selectedPeriod', '2026-09')
            ->call('editObjective', $tertutup->id)
            ->set('editingObjId', $tertutup->id)
            ->set('editActual', 999)
            ->call('updateObjective');

        $this->assertNotEquals(999, $tertutup->fresh()->actual);
    }

    public function test_revenue_of_a_closed_month_is_not_changed_and_bad_keys_are_ignored(): void
    {
        $this->actingAsRole('Super Admin');
        Period::where('period', '2026-08')->update(['status' => 'CLOSED']);
        $sebelum = RevenueTarget::where('period', '2026-08')->value('actual');

        $komponen = Livewire::test(RevenueTargets::class, ['year' => '2026']);
        $rows = $komponen->get('rows');
        $rows['08']['actual'] = '5000';
        $rows['99'] = ['target' => '1', 'actual' => '1'];
        $komponen->set('rows', $rows)->call('save');

        $this->assertEquals($sebelum, RevenueTarget::where('period', '2026-08')->value('actual'));
        $this->assertFalse(RevenueTarget::where('period', '2026-99')->exists());
    }

    /* ------------------------------------------------------------- hak akses */

    public function test_an_entity_bound_admin_cannot_reach_other_entities_or_become_holding_level(): void
    {
        $admin = $this->actingAsRole('Super Admin');
        $aej = Entity::where('code', 'AEJ')->firstOrFail();
        $lain = User::create(['name' => 'Orang AEJ', 'email' => 'aej@contoh.test', 'password' => bcrypt('x'), 'is_active' => true, 'entity_id' => $aej->id]);

        $this->assertThrows(fn () => Livewire::test(ManageUsers::class)->call('openEdit', $lain->id),
            ModelNotFoundException::class);

        Livewire::test(ManageUsers::class)
            ->call('openEdit', $admin->id)
            ->set('entity_id', '')
            ->call('saveUser');

        $this->assertSame($this->erdigma->id, $admin->fresh()->entity_id);
    }

    public function test_the_finance_gateway_needs_the_ratio_permission(): void
    {
        $this->actingAsRole('Admin HRIS');
        $sebelum = AccountBalance::count();

        Livewire::test(SystemIntegration::class)->call('processFinancePayload');

        $this->assertSame($sebelum, AccountBalance::count());
    }

    public function test_switching_period_never_redirects_to_another_host(): void
    {
        $this->actingAsRole('Super Admin');

        $this->withHeader('referer', 'https://evil.example/phish')
            ->post(route('period.switch'), ['period' => '2026-08'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_settings_tab_set_directly_is_still_permission_checked(): void
    {
        $this->actingAsRole('Admin FAT'); // punya manage apikey, tanpa manage settings

        Livewire::test(AppSettings::class)
            ->set('activeTab', 'identity')
            ->assertSet('activeTab', 'api');
    }

    public function test_a_negative_actual_on_a_decreasing_ratio_is_not_perfect(): void
    {
        $this->assertSame(0.0, RatioLibrary::achievement(-2.5, 1.0, 'Turun')); // DER, ekuitas negatif
        $this->assertSame(100.0, RatioLibrary::achievement(0.0, 1.0, 'Turun'));
    }

    public function test_an_entity_bound_admin_cannot_pick_a_unit_of_another_entity(): void
    {
        $this->actingAsRole('Super Admin');

        Livewire::test(ManageUsers::class)
            ->call('openCreate')
            ->assertSet('entity_id', (string) $this->erdigma->id)
            ->set('name', 'Pengguna Baru')->set('email', 'baru@contoh.test')
            ->set('password', 'Sandi#Kuat2026')->set('role', 'Viewer')
            ->set('entity_id', '')->set('dept_code', 'BOGUS-XX')
            ->call('saveUser')
            ->assertHasErrors('dept_code');
    }

    public function test_phasing_keeps_the_annual_total_when_months_are_closed(): void
    {
        $this->actingAsRole('Super Admin');
        RevenuePlan::updateOrCreate(['year' => '2027'], ['approved_target' => 1200, 'revised_target' => null]);
        RevenueForecastPlan::where('year', '2027')->delete();
        RevenueTarget::create(['period' => '2027-01', 'target' => 300, 'actual' => 280]);
        Period::create(['period' => '2027-01', 'status' => 'CLOSED', 'apex_score' => 0]);

        Livewire::test(RevenuePlanning::class, ['year' => '2027'])->call('applyPhasing');

        $this->assertEquals(300, RevenueTarget::where('period', '2027-01')->value('target')); // tidak ditimpa
        $this->assertEqualsWithDelta(1200, RevenueTarget::where('period', 'like', '2027-%')->sum('target'), 0.01);
    }
}
