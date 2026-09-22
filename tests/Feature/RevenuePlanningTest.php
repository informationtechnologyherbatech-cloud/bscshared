<?php

namespace Tests\Feature;

use App\Livewire\RevenuePlanning;
use App\Livewire\RevenueTargets;
use App\Models\Entity;
use App\Models\RevenueForecastPlan;
use App\Models\RevenuePlan;
use App\Models\RevenueTarget;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Halaman Perencanaan Target (L1 bagian A–G) dengan data contoh workbook:
 * realisasi 2026 Jan–Agu tersimpan di menu Target Revenue, target 2027.
 */
class RevenuePlanningTest extends TestCase
{
    use RefreshDatabase;

    private const REALISASI_2026 = [
        '01' => 63e9, '02' => 65e9, '03' => 68e9, '04' => 66e9, '05' => 71e9, '06' => 67e9, '07' => 69e9, '08' => 71e9,
    ];

    private Entity $erdigma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
    }

    private function actingAsRole(string $role, ?Entity $entitas = null): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Pengguna '.$role, 'email' => str($role)->slug().'@contoh.test',
            'password' => bcrypt('Herbatech#2026aman'), 'is_active' => true,
            'entity_id' => ($entitas ?? $this->erdigma)->id,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function seedBaseYear(): void
    {
        foreach (self::REALISASI_2026 as $b => $nilai) {
            RevenueTarget::create(['period' => '2026-'.$b, 'target' => 0, 'actual' => $nilai]);
        }
    }

    private function filled()
    {
        return Livewire::test(RevenuePlanning::class, ['year' => '2027'])
            ->set('history.2023', '560000000000')
            ->set('history.2024', '640000000000')
            ->set('history.2025', '730000000000')
            ->call('addBrand')->call('addBrand')->call('addBrand')
            ->set('brands.0.name', 'Eyebost')
            ->set('brands.0.cells', ['190000000000', '145000000000', '75000000000', '48000000000', '18000000000'])
            ->set('brands.0.growth', '12')
            ->set('brands.1.name', 'Brand 2')
            ->set('brands.1.cells', ['96000000000', '76000000000', '38000000000', '19000000000', '9000000000'])
            ->set('brands.1.growth', '15')
            ->set('brands.2.name', 'Brand 3')
            ->set('brands.2.cells', ['38000000000', '29000000000', '19000000000', '10000000000', '0'])
            ->set('brands.2.growth', '20')
            ->call('addInitiative')->call('addInitiative')->call('addInitiative')
            ->set('ansoff.0.revenue', '25000000000')->set('ansoff.0.probability', '70')
            ->set('ansoff.1.quadrant', 'pasar')->set('ansoff.1.revenue', '40000000000')->set('ansoff.1.probability', '40')
            ->set('ansoff.2.quadrant', 'produk')->set('ansoff.2.revenue', '50000000000')->set('ansoff.2.probability', '50');
    }

    public function test_the_page_reproduces_the_workbook_from_saved_data(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedBaseYear();

        $this->filled()
            ->assertSet('channels', ['SOC', 'TTC', 'ECO', 'OFD', 'PTN']) // bawaan entitas digital marketing
            ->assertViewHas('r', function ($r) {
                return abs($r['estimate'] - 810e9) < 1
                    && abs($r['methods']['cagr']['value'] - 916046140972) < 1
                    && abs($r['methods']['regression']['value'] - 895e9) < 1
                    && abs($r['methods']['bottomup']['value'] - 922020000000) < 1
                    && abs($r['methods']['ambitious']['value'] - 980520000000) < 1
                    && abs($r['methods']['average']['value'] - 911022046991) < 1
                    && $r['base_consistent'] === true;
            });
    }

    public function test_approving_and_phasing_fill_the_monthly_targets(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedBaseYear();

        $this->filled()
            ->set('manualApproval', '900000000000')
            ->call('approve', 'manual')
            ->call('applyPhasing');

        $this->assertEqualsWithDelta(900e9, RevenuePlan::where('year', '2027')->value('approved_target'), 0.01);
        $target = RevenueTarget::where('period', 'like', '2027-%')->orderBy('period')->pluck('target', 'period');
        $this->assertCount(12, $target);
        $this->assertEqualsWithDelta(70e9, (float) $target['2027-01'], 1);
        $this->assertEqualsWithDelta(78888888889, (float) $target['2027-08'], 1);
        $this->assertEqualsWithDelta(75e9, (float) $target['2027-12'], 1);
        $this->assertEqualsWithDelta(900e9, (float) $target->sum(), 0.01);
    }

    public function test_approving_a_method_keeps_an_existing_revision(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedBaseYear();
        RevenuePlan::create(['year' => '2027', 'approved_target' => 800e9, 'revised_target' => 760e9]);

        $this->filled()->call('approve', 'bottomup');

        $plan = RevenuePlan::where('year', '2027')->first();
        $this->assertEqualsWithDelta(922020000000, $plan->approved_target, 0.01);
        $this->assertEqualsWithDelta(760e9, $plan->revised_target, 0.01);
    }

    public function test_phasing_keeps_actuals_already_entered(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedBaseYear();
        RevenueTarget::create(['period' => '2027-01', 'target' => 1, 'actual' => 68e9]);
        RevenuePlan::create(['year' => '2027', 'approved_target' => 900e9]);

        Livewire::test(RevenuePlanning::class, ['year' => '2027'])->call('applyPhasing');

        $jan = RevenueTarget::where('period', '2027-01')->first();
        $this->assertEqualsWithDelta(70e9, (float) $jan->target, 1);
        $this->assertEqualsWithDelta(68e9, (float) $jan->actual, 1);
    }

    public function test_the_plan_is_saved_and_reloaded(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedBaseYear();

        $this->filled()
            ->set('swot.t', 'Perubahan algoritma platform')
            ->set('swotAdjustment', '-3')
            ->call('save')
            ->assertHasNoErrors();

        $plan = RevenueForecastPlan::where('year', '2027')->firstOrFail();
        $this->assertSame($this->erdigma->id, $plan->entity_id);
        $this->assertEqualsWithDelta(0.12, $plan->brands[0]['growth'], 1e-12);
        $this->assertEqualsWithDelta(0.7, $plan->ansoff[0]['probability'], 1e-12);

        Livewire::test(RevenuePlanning::class, ['year' => '2027'])
            ->assertSet('brands.0.name', 'Eyebost')
            ->assertSet('brands.0.growth', '12')
            ->assertSet('ansoff.1.quadrant', 'pasar')
            ->assertSet('swotAdjustment', '-3')
            ->assertViewHas('r', fn ($r) => abs($r['methods']['ambitious']['value'] - 980520000000 * 0.97) < 1);
    }

    public function test_removing_a_channel_drops_its_column_from_every_brand(): void
    {
        $this->actingAsRole('Admin FAT');

        $komponen = $this->filled()->call('removeChannel', 1); // TTC

        $this->assertSame(['SOC', 'ECO', 'OFD', 'PTN'], $komponen->get('channels'));
        $this->assertSame(['190000000000', '75000000000', '48000000000', '18000000000'], $komponen->get('brands.0.cells'));
    }

    public function test_manufacturing_entities_start_without_channels(): void
    {
        $this->actingAsRole('Admin FAT', Entity::where('code', 'HERBATECH')->firstOrFail());

        Livewire::test(RevenuePlanning::class, ['year' => '2027'])->assertSet('channels', []);
    }

    public function test_viewers_can_look_but_not_approve(): void
    {
        $this->actingAsRole('Viewer');
        $this->seedBaseYear();

        $this->get(route('revenue-planning'))->assertOk();

        Livewire::test(RevenuePlanning::class, ['year' => '2027'])
            ->set('manualApproval', '900000000000')
            ->call('approve', 'manual')
            ->call('save');

        $this->assertSame(0, RevenuePlan::count());
        $this->assertSame(0, RevenueForecastPlan::count());
    }

    public function test_the_revenue_page_phases_from_a_partial_base_year(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedBaseYear();

        $rows = Livewire::test(RevenueTargets::class, ['year' => '2027'])
            ->set('annualTarget', '900000000000')
            ->call('phaseBySeason')
            ->assertHasNoErrors()
            ->get('rows');

        // Sama dengan sheet L1 bagian G.
        $this->assertEqualsWithDelta(70e9, (float) $rows['01']['target'], 1);
        $this->assertEqualsWithDelta(75e9, (float) $rows['09']['target'], 1);
    }
}
