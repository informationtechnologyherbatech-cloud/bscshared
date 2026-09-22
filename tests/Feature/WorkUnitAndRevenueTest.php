<?php

namespace Tests\Feature;

use App\Livewire\ActionPlans;
use App\Livewire\RevenueTargets;
use App\Livewire\WorkUnits;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\RevenueTarget;
use App\Models\User;
use App\Models\WorkUnit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkUnitAndRevenueTest extends TestCase
{
    use RefreshDatabase;

    private Entity $erdigma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
    }

    private function actingAsRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Pengguna '.$role,
            'email' => str($role)->slug().'@contoh.test',
            'password' => bcrypt('Herbatech#2026aman'),
            'is_active' => true,
            'entity_id' => $this->erdigma->id,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /* ============================================================ UNIT KERJA */

    public function test_a_unit_can_be_added(): void
    {
        $this->actingAsRole('Super Admin');

        Livewire::test(WorkUnits::class)
            ->call('openCreate')
            ->set('code', 'lvs')
            ->set('name', 'Live Streaming Studio')
            ->set('stream', 'Downstream EDX & EDM')
            ->call('save')
            ->assertHasNoErrors();

        // Kode disimpan huruf besar dan menjadi milik entitas aktif.
        $unit = WorkUnit::where('code', 'LVS')->firstOrFail();
        $this->assertSame($this->erdigma->id, $unit->entity_id);
    }

    public function test_a_unit_name_can_be_changed(): void
    {
        $this->actingAsRole('Super Admin');
        $unit = WorkUnit::where('code', 'ERS')->firstOrFail();

        Livewire::test(WorkUnits::class)
            ->call('openEdit', $unit->id)
            ->set('name', 'Erspace — Coworking & Event')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Erspace — Coworking & Event', $unit->fresh()->name);
    }

    public function test_a_code_must_be_unique_within_the_entity(): void
    {
        $this->actingAsRole('Super Admin');

        Livewire::test(WorkUnits::class)
            ->call('openCreate')
            ->set('code', 'SCM')
            ->set('name', 'Duplikat')
            ->call('save')
            ->assertHasErrors('code');
    }

    public function test_the_same_code_may_exist_in_another_entity(): void
    {
        // SCM ada di Erdigma maupun di ketiga entitas manufaktur.
        $this->assertSame(4, WorkUnit::withoutGlobalScopes()->where('code', 'SCM')->count());
    }

    public function test_a_code_in_use_cannot_be_renamed(): void
    {
        $this->actingAsRole('Super Admin');
        DepartmentObjective::create([
            'period' => '2026-08', 'dept_code' => 'SCM', 'kpi_code' => 'SCM-H01', 'kpi_name' => 'Service level',
            'polarity' => 'Naik', 'target' => 100, 'actual' => 90, 'achievement_pct' => 90, 'status' => 'Waspada',
        ]);
        $unit = WorkUnit::where('code', 'SCM')->firstOrFail();

        Livewire::test(WorkUnits::class)
            ->call('openEdit', $unit->id)
            ->assertSet('codeLocked', true)
            ->set('code', 'SCX')
            ->call('save')
            ->assertHasErrors('code');

        $this->assertSame('SCM', $unit->fresh()->code);
    }

    public function test_a_unit_in_use_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $this->actingAsRole('Super Admin');
        DepartmentObjective::create([
            'period' => '2026-08', 'dept_code' => 'TTC', 'kpi_code' => 'TTC-H01', 'kpi_name' => 'Revenue TikTok',
            'polarity' => 'Naik', 'target' => 100, 'actual' => 90, 'achievement_pct' => 90, 'status' => 'Waspada',
        ]);
        $unit = WorkUnit::where('code', 'TTC')->firstOrFail();

        Livewire::test(WorkUnits::class)
            ->call('confirmDelete', $unit->id)
            ->call('delete');
        $this->assertNotNull($unit->fresh());

        Livewire::test(WorkUnits::class)->call('toggleActive', $unit->id);
        $this->assertFalse($unit->fresh()->is_active);
    }

    public function test_an_unused_unit_can_be_deleted(): void
    {
        $this->actingAsRole('Super Admin');
        $unit = WorkUnit::where('code', 'SEC')->firstOrFail();

        Livewire::test(WorkUnits::class)
            ->call('confirmDelete', $unit->id)
            ->call('delete');

        $this->assertNull($unit->fresh());
    }

    public function test_only_permitted_roles_can_manage_units(): void
    {
        $this->actingAsRole('Viewer');

        $this->get(route('work-units'))->assertForbidden();
    }

    public function test_admin_hris_can_manage_units(): void
    {
        $this->actingAsRole('Admin HRIS');

        $this->get(route('work-units'))->assertOk()->assertSee('TikTok Commerce', false);
    }

    public function test_an_action_plan_owner_must_be_an_active_unit(): void
    {
        $this->actingAsRole('Super Admin');

        Livewire::test(ActionPlans::class)
            ->set('title', 'Perluasan affiliate TikTok')
            ->set('ownerDept', 'NGAWUR')
            ->call('createPlan')
            ->assertHasErrors('ownerDept');

        Livewire::test(ActionPlans::class)
            ->set('title', 'Perluasan affiliate TikTok')
            ->set('ownerDept', 'TTC')
            ->call('createPlan')
            ->assertHasNoErrors();
    }

    /* ======================================================== TARGET REVENUE */

    public function test_monthly_revenue_can_be_saved(): void
    {
        $this->actingAsRole('Super Admin');

        Livewire::test(RevenueTargets::class)
            ->set('year', '2027')
            ->set('rows.01.target', '70000000000')
            ->set('rows.01.actual', '63000000000')
            ->set('rows.02.target', '72000000000')
            ->call('save')
            ->assertHasNoErrors();

        $jan = RevenueTarget::where('period', '2027-01')->firstOrFail();
        $this->assertSame(70000000000.0, (float) $jan->target);
        $this->assertSame(63000000000.0, (float) $jan->actual);
        $this->assertSame($this->erdigma->id, $jan->entity_id);

        // Bulan kosong tidak dibuatkan baris.
        $this->assertNull(RevenueTarget::where('period', '2027-03')->first());
    }

    public function test_an_annual_target_can_be_phased_evenly(): void
    {
        $this->actingAsRole('Super Admin');

        $komponen = Livewire::test(RevenueTargets::class)
            ->set('year', '2027')
            ->set('annualTarget', '900000000000')
            ->call('phaseEvenly');

        $total = collect($komponen->get('rows'))->sum(fn ($r) => (float) $r['target']);
        $this->assertEqualsWithDelta(900000000000, $total, 0.01);
    }

    public function test_seasonal_phasing_needs_previous_year_realisations(): void
    {
        $this->actingAsRole('Super Admin');

        Livewire::test(RevenueTargets::class)
            ->set('year', '2027')
            ->set('annualTarget', '900000000000')
            ->call('phaseBySeason')
            ->assertHasErrors('annualTarget');
    }

    public function test_seasonal_phasing_follows_last_years_pattern(): void
    {
        $this->actingAsRole('Super Admin');

        // Realisasi 2026: Desember dua kali bulan lain.
        foreach (range(1, 12) as $b) {
            RevenueTarget::create([
                'period' => sprintf('2026-%02d', $b),
                'target' => 100,
                'actual' => $b === 12 ? 200 : 100,
            ]);
        }

        $rows = Livewire::test(RevenueTargets::class)
            ->set('year', '2027')
            ->set('annualTarget', '1300')
            ->call('phaseBySeason')
            ->assertHasNoErrors()
            ->get('rows');

        $this->assertEqualsWithDelta(100, (float) $rows['01']['target'], 0.01);
        $this->assertEqualsWithDelta(200, (float) $rows['12']['target'], 0.01);
    }

    public function test_a_viewer_can_see_but_not_change_revenue(): void
    {
        $this->actingAsRole('Viewer');

        $this->get(route('revenue'))->assertOk()->assertDontSee('Bantu fasing target setahun', false);

        Livewire::test(RevenueTargets::class)
            ->set('year', '2027')
            ->set('rows.01.target', '1000')
            ->call('save');

        $this->assertNull(RevenueTarget::where('period', '2027-01')->first());
    }
}
