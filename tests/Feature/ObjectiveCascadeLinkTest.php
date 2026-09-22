<?php

namespace Tests\Feature;

use App\Livewire\KpiCascades;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\KpiCascade;
use App\Models\User;
use App\Support\Bsc\CascadeChecks;
use App\Support\Bsc\PostMap;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sasaran di Objective Departemen harus tertelusur ke Cascade KPI: data
 * contoh dibuat lewat cascade, dan data lama dapat diambil menjadi draf KPI.
 */
class ObjectiveCascadeLinkTest extends TestCase
{
    use RefreshDatabase;

    private Entity $erdigma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
    }

    private function actingAsRole(string $role): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::create([
            'name' => $role, 'email' => str($role)->slug().'@contoh.test', 'password' => bcrypt('Herbatech#2026aman'),
            'is_active' => true, 'entity_id' => $this->erdigma->id,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);
    }

    public function test_sample_objectives_come_from_approved_cascade_kpis(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(EntityContext::class)->use($this->erdigma->id);

        $this->assertSame(8, KpiCascade::where('year', '2026')->where('validation_status', KpiCascade::LOLOS)->count());
        $this->assertSame(0, DepartmentObjective::whereNull('kpi_cascade_id')->count());

        // Tiap sasaran tertaut ke KPI dengan kode & unit yang sama.
        foreach (DepartmentObjective::with('kpiCascade')->get() as $o) {
            $this->assertSame($o->kpi_code, $o->kpiCascade->code);
            $this->assertSame($o->dept_code, $o->kpiCascade->unit_code);
        }
    }

    public function test_sample_cascade_kpis_pass_every_check_for_erdigma(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(EntityContext::class)->use($this->erdigma->id);

        $semua = KpiCascade::where('year', '2026')->get();
        $cek = new CascadeChecks($semua, new PostMap);

        foreach ($semua as $kpi) {
            $this->assertSame([], $cek->forRow($kpi)['issues'], $kpi->code.' seharusnya lolos semua cek.');
        }
        $this->assertSame('OK', $cek->unitSummary(['SCM'])['SCM']['head_check']);
        $this->assertSame('OK', $cek->unitSummary(['BMK'])['BMK']['head_check']);
    }

    public function test_unlinked_objectives_become_draft_kpis(): void
    {
        $this->actingAsRole('Kepala Departemen');
        foreach (['2026-07', '2026-08'] as $p) {
            DepartmentObjective::create([
                'period' => $p, 'dept_code' => 'SCM', 'kpi_code' => 'OPS 02/lama', 'kpi_name' => 'Kontribusi biaya operasional',
                'polarity' => 'Turun', 'target' => $p === '2026-08' ? 5 : 6, 'actual' => 4,
            ]);
        }
        DepartmentObjective::create(['period' => '2025-12', 'dept_code' => 'SCM', 'kpi_code' => 'LAIN-TAHUN', 'kpi_name' => 'x', 'target' => 1, 'actual' => 1]);

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->assertViewHas('unlinked', 2)
            ->call('adoptObjectives')
            ->assertViewHas('unlinked', 0);

        $kpi = KpiCascade::where('year', '2026')->firstOrFail();
        $this->assertSame('OPS-02-LAMA', $kpi->code);        // kode dibersihkan
        $this->assertSame(5.0, $kpi->target);                // dari periode terbaru
        $this->assertSame('Turun', $kpi->polarity);
        $this->assertSame(KpiCascade::BELUM_DIUJI, $kpi->validation_status);
        $this->assertSame(1, KpiCascade::count());           // tahun lain tidak ikut

        // Kedua bulan tertaut, realisasinya tidak berubah.
        $objektif = DepartmentObjective::where('period', 'like', '2026-%')->get();
        $this->assertTrue($objektif->every(fn ($o) => $o->kpi_cascade_id === $kpi->id && $o->kpi_code === 'OPS-02-LAMA'));
        $this->assertTrue($objektif->every(fn ($o) => (float) $o->actual === 4.0));

        // Draf sengaja belum lengkap → ditandai untuk dilengkapi.
        $isu = (new CascadeChecks(KpiCascade::all(), new PostMap))->forRow($kpi)['issues'];
        $this->assertNotEmpty($isu);
    }

    public function test_an_existing_kpi_with_the_same_code_is_reused(): void
    {
        $this->actingAsRole('Kepala Departemen');
        $ada = KpiCascade::create([
            'year' => '2026', 'code' => 'SCM-01', 'unit_code' => 'SCM', 'level' => 'Head', 'position' => 'Kepala SCM',
            'objective' => 'Service level', 'measure_type' => 'Lag', 'weight' => 100, 'kpi_type' => 'Driver',
        ]);
        DepartmentObjective::create(['period' => '2026-08', 'dept_code' => 'SCM', 'kpi_code' => 'SCM-01', 'kpi_name' => 'Service level', 'target' => 90, 'actual' => 85]);

        Livewire::test(KpiCascades::class, ['year' => '2026'])->call('adoptObjectives');

        $this->assertSame(1, KpiCascade::count());
        $this->assertSame($ada->id, DepartmentObjective::value('kpi_cascade_id'));
    }

    public function test_viewers_cannot_adopt(): void
    {
        $this->actingAsRole('Viewer');
        DepartmentObjective::create(['period' => '2026-08', 'dept_code' => 'SCM', 'kpi_code' => 'SCM-01', 'kpi_name' => 'x', 'target' => 1, 'actual' => 1]);

        Livewire::test(KpiCascades::class, ['year' => '2026'])->call('adoptObjectives');

        $this->assertSame(0, KpiCascade::count());
    }
}
