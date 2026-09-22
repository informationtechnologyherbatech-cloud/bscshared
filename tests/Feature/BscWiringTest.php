<?php

namespace Tests\Feature;

use App\Livewire\BscWiring;
use App\Models\Entity;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Wiring harus mencerminkan data entitas aktif — unit kerjanya, sasaran
 * periodenya, dan hubungan dari Cascade KPI — bukan daftar tetap Herbatech.
 */
class BscWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class); // data contoh Erdigma, lewat cascade
        $this->actingAs(User::create([
            'name' => 'Staf Erdigma', 'email' => 'staf@contoh.test', 'password' => bcrypt('x'), 'is_active' => true,
            'entity_id' => Entity::where('code', 'ERDIGMA')->value('id'),
        ])->assignRole('Super Admin'));
    }

    private function dept(array $departments, string $kode): array
    {
        return collect($departments)->firstWhere('code', $kode);
    }

    public function test_units_are_the_active_entitys_own(): void
    {
        Livewire::test(BscWiring::class)
            ->assertSet('selectedPeriod', '2026-08')
            ->assertViewHas('departments', function ($d) {
                $kode = collect($d)->pluck('code');

                return $kode->count() === 17 && $kode->contains('TTC') && $kode->contains('SCM') && ! $kode->contains('PROC');
            })
            ->assertDontSee('Procurement')
            ->assertDontSee('Operation Manager');
    }

    public function test_counts_come_from_real_objectives(): void
    {
        Livewire::test(BscWiring::class)
            ->assertViewHas('objectiveCount', 8)
            ->assertViewHas('departments', function ($d) {
                $scm = $this->dept($d, 'SCM');
                $ttc = $this->dept($d, 'TTC');

                // SCM: 6 sasaran, 2 Waspada; unit tanpa data tidak diberi angka palsu.
                return $scm['sasaran'] === 6 && $scm['bergeser'] === 2 && count($scm['dots']) === 6
                    && $ttc['sasaran'] === 0 && $ttc['dots'] === [];
            });
    }

    public function test_links_come_from_the_cascade_and_the_map(): void
    {
        Livewire::test(BscWiring::class)
            ->assertViewHas('departments', function ($d) {
                $scm = $this->dept($d, 'SCM');
                $bmk = $this->dept($d, 'BMK');

                // KPI contoh SCM mengklaim REV, P1 (Profitabilitas), A1 (Aktivitas).
                return collect($scm['links'])->sort()->values()->all() === ['Aktivitas', 'Profitabilitas', 'revenue']
                    && $bmk['links'] === ['revenue']
                    // Peta: SCM juga Pemilik/Kontributor pos pembentuk Likuiditas & Produktivitas.
                    && in_array('Likuiditas', $scm['potential'], true)
                    && ! in_array('revenue', $scm['potential'], true);
            })
            ->assertSeeHtml('data-links="')
            ->assertDontSeeHtml('data-potential="Likuiditas');

        Livewire::test(BscWiring::class)
            ->set('showPotential', true)
            ->assertSeeHtml('Likuiditas');
    }

    public function test_perspectives_follow_the_old_wiring_terms_and_order(): void
    {
        Livewire::test(BscWiring::class)
            ->assertViewHas('perspectives', fn ($p) => collect($p)->pluck('name')->all()
                === ['Profitabilitas', 'Revenue / Pertumbuhan', 'Aktivitas', 'Produktivitas', 'Likuiditas', 'Solvabilitas'])
            ->assertSee('Revenue / Pertumbuhan');
    }

    public function test_the_unit_filter_and_shifted_filter_apply(): void
    {
        Livewire::test(BscWiring::class)
            ->set('selectedUnit', 'SCM')
            ->assertViewHas('filteredDepartments', fn ($d) => collect($d)->pluck('code')->all() === ['SCM'])
            ->set('selectedUnit', 'all')
            ->set('hanyaBergeser', true) // SCM (2 Waspada) & BMK (BMK-02 Waspada)
            ->assertViewHas('filteredDepartments', fn ($d) => collect($d)->pluck('code')->sort()->values()->all() === ['BMK', 'SCM']);
    }

    public function test_the_cascade_slider_adjusts_targets_by_elasticity(): void
    {
        Livewire::test(BscWiring::class)
            ->call('switchTab', 'cascade')
            ->set('cascadeValue', 90)
            ->assertViewHas('deptCards', function ($cards) {
                $kpi = collect($cards)->firstWhere('code', 'SCM')['kpis'];
                $sl = collect($kpi)->firstWhere('code', 'SCM-01');   // e = 1
                $rft = collect($kpi)->firstWhere('code', 'SCM-04');  // guardrail

                return abs($sl['adjusted'] - 81.0) < 1e-9 && $rft['elasticity'] === 'dikunci' && $rft['adjusted'] === $rft['target'];
            })
            ->assertSee('Wiring Sasaran Mutu (8 KPI)');
    }
}
