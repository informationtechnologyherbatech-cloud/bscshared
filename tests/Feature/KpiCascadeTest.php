<?php

namespace Tests\Feature;

use App\Livewire\DepartmentObjectives;
use App\Livewire\KpiCascades;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\KpiCascade;
use App\Models\Period;
use App\Models\User;
use App\Support\Bsc\CascadeChecks;
use App\Support\Bsc\PostMap;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cascade KPI (L3). Data uji = sheet "Contoh Terisi" (SCM, TTC Eyebost, CMP);
 * kolom auto dan ringkasan per unit harus sama dengan workbook.
 */
class KpiCascadeTest extends TestCase
{
    use RefreshDatabase;

    /** [kode, unit, brand, level, induk, jabatan, sasaran, ukuran, target, satuan, bobot %, jenis, e, rasio, pos, arah, individu] */
    private const CONTOH = [
        ['SCM-H01', 'SCM', null, 'Head', null, 'Supply Chain Manager', 'Service level ke seluruh channel', 'Lag', 1, '%', 50, 'Driver', 1, 'REV', 'PA01', 'Menaikkan', null],
        ['SCM-H02', 'SCM', null, 'Head', null, 'Supply Chain Manager', 'Perputaran persediaan FC (ITO)', 'Lag', 6, 'x', 50, 'Driver', 0.8, 'A1', 'PA05', 'Menurunkan', null],
        ['SCM-S01', 'SCM', null, 'Supervisor', 'SCM-H01', 'Spv Fulfillment Center', 'OTIF fulfillment', 'Lead', 0.97, '%', 100, 'Driver', 0.8, 'REV', 'PA01', 'Menaikkan', null],
        ['SCM-S02', 'SCM', null, 'Supervisor', 'SCM-H01', 'Spv Last Mile Delivery', 'Last-mile on-time delivery rate', 'Lead', 0.95, '%', 100, 'Driver', 0.5, 'REV', 'PA01', 'Menaikkan', null],
        ['SCM-S03', 'SCM', null, 'Supervisor', 'SCM-H02', 'Spv Demand Planning', 'Forecast accuracy per SKU', 'Lead', 0.85, '%', 100, 'Driver', 0.8, 'A1', 'PA05', 'Menurunkan', null],
        ['SCM-T01', 'SCM', null, 'Staff', 'SCM-S01', 'Staf Fulfillment Center', 'Akurasi cycle count harian', 'Output', 0.98, '%', 65, 'Driver', 0.5, 'A1', 'PA05', 'Menurunkan', 'Rutin'],
        ['SCM-T02', 'SCM', null, 'Staff', 'SCM-S01', 'Staf Fulfillment Center', 'Standarisasi lokasi & labeling FC', 'Output', 1, '% selesai', 35, 'Driver', 0, 'A1', 'PA05', 'Menurunkan', 'Milestone'],
        ['SCM-T03', 'SCM', null, 'Staff', 'SCM-S03', 'Staf Demand Planning', 'S&OP bulanan tepat waktu', 'Output', 1, '% bulan', 100, 'Driver', 0, 'A1', 'PA05', 'Menurunkan', 'Rutin'],
        ['TTC-H01', 'TTC', 'Eyebost', 'Head', null, 'PGM TikTok Commerce — Eyebost', 'Revenue TikTok Commerce Eyebost', 'Lag', 162.4e9, 'Rp', 60, 'Driver', 1, 'REV', 'PA01', 'Menaikkan', null],
        ['TTC-H02', 'TTC', 'Eyebost', 'Head', null, 'PGM TikTok Commerce — Eyebost', 'Ad cost ratio', 'Lag', 0.2, '% maks', 40, 'Driver', 0.5, 'P2', 'PA03', 'Menurunkan', null],
        ['TTC-S01', 'TTC', 'Eyebost', 'Supervisor', 'TTC-H01', 'Live Commerce Lead', 'GMV live per bulan', 'Lead', 5.5e9, 'Rp / bulan', 100, 'Driver', 1, 'REV', 'PA01', 'Menaikkan', null],
        ['TTC-S02', 'TTC', 'Eyebost', 'Supervisor', 'TTC-H01', 'Affiliate Lead', 'Affiliate aktif', 'Lead', 300, 'orang', 100, 'Driver', 0.8, 'REV', 'PA01', 'Menaikkan', null],
        ['TTC-S03', 'TTC', 'Eyebost', 'Supervisor', 'TTC-H02', 'Ads Specialist Lead', 'ROAS campaign TikTok Ads', 'Lead', 5, 'x', 100, 'Driver', 0.5, 'P2', 'PA03', 'Menurunkan', null],
        ['TTC-T01', 'TTC', 'Eyebost', 'Staff', 'TTC-S01', 'Host live', 'Jam live sesuai jadwal', 'Output', 0.95, '%', 100, 'Driver', 0, 'REV', 'PA01', 'Menaikkan', 'Rutin'],
        ['TTC-T02', 'TTC', 'Eyebost', 'Staff', 'TTC-S02', 'Affiliate specialist', 'Affiliate baru teraktivasi', 'Output', 50, 'orang/bulan', 100, 'Driver', 0, 'REV', 'PA01', 'Menaikkan', 'Rutin'],
        ['TTC-T03', 'TTC', 'Eyebost', 'Staff', 'TTC-S03', 'Content creator', 'Konten iklan baru tayang', 'Output', 20, 'konten/bulan', 100, 'Driver', 0, 'P2', 'PA03', 'Menurunkan', 'Rutin'],
        ['CMP-H01', 'CMP', null, 'Head', null, 'Head Compliance & Regulatory', 'Temuan iklan overclaim', 'Lag', 0, 'temuan', 50, 'Guardrail', 0, null, null, null, null],
        ['CMP-H02', 'CMP', null, 'Head', null, 'Head Compliance & Regulatory', 'Kasus pemalsu tertangani ≤ 7 hari', 'Lag', 1, '%', 50, 'Guardrail', 0, null, null, null, null],
    ];

    private Entity $erdigma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        app(EntityContext::class)->use($this->erdigma->id);
    }

    private function seedExamples(string $status = KpiCascade::LOLOS): void
    {
        foreach (self::CONTOH as $i => $r) {
            KpiCascade::create([
                'year' => '2026', 'code' => $r[0], 'unit_code' => $r[1], 'brand' => $r[2], 'level' => $r[3],
                'parent_code' => $r[4], 'position' => $r[5], 'objective' => $r[6], 'measure_type' => $r[7],
                'target' => $r[8], 'unit_label' => $r[9], 'weight' => $r[10], 'kpi_type' => $r[11],
                'elasticity' => $r[12], 'ratio_code' => $r[13], 'post_code' => $r[14], 'direction' => $r[15],
                'individual_type' => $r[16], 'validation_status' => $status, 'sort' => $i,
            ]);
        }
    }

    private function checks(): CascadeChecks
    {
        return new CascadeChecks(KpiCascade::where('year', '2026')->get(), new PostMap);
    }

    private function actingAsRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Pengguna '.$role, 'email' => str($role)->slug().'@contoh.test',
            'password' => bcrypt('Herbatech#2026aman'), 'is_active' => true, 'entity_id' => $this->erdigma->id,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /* ======================================================= kolom auto */

    public function test_the_worked_examples_pass_every_check(): void
    {
        $this->seedExamples();
        $cek = $this->checks();

        foreach (KpiCascade::all() as $kpi) {
            $this->assertSame([], $cek->forRow($kpi)['issues'], $kpi->code.' seharusnya lolos semua cek.');
        }
    }

    public function test_the_role_column_matches_the_workbook(): void
    {
        $this->seedExamples();
        $cek = $this->checks();
        $peran = fn (string $kode) => $cek->forRow(KpiCascade::where('code', $kode)->first())['role'];

        // "Peran unit di pos akun (auto dari Peta)".
        $this->assertSame('Kontributor', $peran('SCM-H01'));
        $this->assertSame('Pemilik', $peran('SCM-H02'));
        $this->assertSame('Pemilik', $peran('TTC-H01'));
        $this->assertSame('Kontributor', $peran('TTC-H02'));
        $this->assertNull($peran('CMP-H01'));
    }

    public function test_the_unit_summary_matches_the_workbook(): void
    {
        $this->seedExamples();
        $ringkas = $this->checks()->unitSummary(['SCM', 'CMP', 'TTC', 'FAT']);

        $this->assertSame(['head_weight' => 100.0, 'head_check' => 'OK', 'supervisors' => 3, 'supervisor_bad' => 0, 'staff' => 3, 'staff_bad' => 0, 'brands' => 0], $ringkas['SCM']);
        $this->assertSame(['head_weight' => 100.0, 'head_check' => 'OK', 'supervisors' => 0, 'supervisor_bad' => 0, 'staff' => 0, 'staff_bad' => 0, 'brands' => 0], $ringkas['CMP']);
        $this->assertSame(['head_weight' => 100.0, 'head_check' => 'OK', 'supervisors' => 3, 'supervisor_bad' => 0, 'staff' => 3, 'staff_bad' => 0, 'brands' => 1], $ringkas['TTC']);
        $this->assertSame('—', $ringkas['FAT']['head_check']);
    }

    public function test_weights_are_summed_per_person(): void
    {
        $this->seedExamples();
        // Staf FC punya dua KPI: 65% + 35%.
        KpiCascade::where('code', 'SCM-T02')->update(['weight' => 30]);
        $cek = $this->checks();

        $baris = $cek->forRow(KpiCascade::where('code', 'SCM-T01')->first());
        $this->assertFalse($baris['weight_ok']);
        $this->assertSame(95.0, $baris['weight_sum']);
        $this->assertSame(2, $cek->unitSummary(['SCM'])['SCM']['staff_bad']);
    }

    public function test_each_brand_head_must_reach_one_hundred_percent(): void
    {
        $this->seedExamples();
        KpiCascade::create([
            'year' => '2026', 'code' => 'TTC-H03', 'unit_code' => 'TTC', 'brand' => 'Brand 2', 'level' => 'Head',
            'position' => 'PGM TikTok Commerce — Brand 2', 'objective' => 'Revenue Brand 2', 'measure_type' => 'Lag',
            'weight' => 60, 'kpi_type' => 'Driver', 'ratio_code' => 'REV', 'post_code' => 'PA01',
        ]);

        $this->assertSame('≠100% per brand', $this->checks()->unitSummary(['TTC'])['TTC']['head_check']);
    }

    public function test_invalid_claims_are_flagged(): void
    {
        $this->seedExamples();

        // PA05 bukan pembentuk P2; DIT bukan Pemilik/Kontributor PA05; Staff tanpa induk yang sah.
        KpiCascade::where('code', 'TTC-H02')->update(['post_code' => 'PA05']);
        KpiCascade::create([
            'year' => '2026', 'code' => 'DIT-H01', 'unit_code' => 'DIT', 'level' => 'Head', 'position' => 'Head DIT',
            'objective' => 'Akurasi stok sistem', 'measure_type' => 'Output', 'weight' => 100, 'kpi_type' => 'Driver',
            'ratio_code' => 'A1', 'post_code' => 'PA05',
        ]);
        KpiCascade::create([
            'year' => '2026', 'code' => 'SCM-T09', 'unit_code' => 'SCM', 'level' => 'Staff', 'parent_code' => 'SCM-H01',
            'position' => 'Staf lain', 'objective' => 'X', 'measure_type' => 'Output', 'weight' => 100, 'kpi_type' => 'Driver',
            'ratio_code' => 'A1', 'post_code' => 'PA05',
        ]);
        $cek = $this->checks();
        $isu = fn (string $kode) => implode(' | ', $cek->forRow(KpiCascade::where('code', $kode)->first())['issues']);

        $this->assertStringContainsString('tidak ada di rumus P2', $isu('TTC-H02'));
        $this->assertStringContainsString('tidak terpetakan pada PA05', $isu('DIT-H01'));
        $this->assertStringContainsString('seharusnya memakai ukuran Lag', $isu('DIT-H01'));
        $this->assertStringContainsString('tidak ditemukan sebagai Supervisor', $isu('SCM-T09'));
    }

    public function test_the_tree_lists_each_head_with_its_descendants(): void
    {
        $this->seedExamples();
        $urutan = collect($this->checks()->tree())
            ->filter(fn ($n) => $n['row']->unit_code === 'SCM')
            ->map(fn ($n) => str_repeat('-', $n['depth']).$n['row']->code)
            ->values()->all();

        $this->assertSame(['SCM-H01', '-SCM-S01', '--SCM-T01', '--SCM-T02', '-SCM-S02', 'SCM-H02', '-SCM-S03', '--SCM-T03'], $urutan);
    }

    /* ============================================================ layar */

    public function test_a_unit_can_add_a_head_kpi_with_an_auto_code(): void
    {
        $this->actingAsRole('Kepala Departemen');

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->call('openCreate', 'SCM')
            ->assertSet('form.code', 'SCM-H01')
            ->assertSet('form.measure_type', 'Lag')
            ->set('form.position', 'Supply Chain Manager')
            ->set('form.objective', 'Service level ke seluruh channel')
            ->set('form.weight', '100')
            ->set('form.ratio_code', 'REV')
            ->set('form.post_code', 'PA01')
            ->set('form.validation_status', 'Lolos') // bukan wewenang Kadep — diabaikan
            ->call('save')
            ->assertHasNoErrors();

        $kpi = KpiCascade::where('code', 'SCM-H01')->firstOrFail();
        $this->assertSame($this->erdigma->id, $kpi->entity_id);
        $this->assertSame('2026', $kpi->year);
        $this->assertSame(KpiCascade::BELUM_DIUJI, $kpi->validation_status);
    }

    public function test_a_child_kpi_inherits_its_parent(): void
    {
        $this->actingAsRole('Kepala Departemen');
        $this->seedExamples();

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->call('openCreate', 'TTC', 'Supervisor', 'TTC-H02')
            ->assertSet('form.code', 'TTC-S04')
            ->assertSet('form.brand', 'Eyebost')
            ->assertSet('form.ratio_code', 'P2')
            ->assertSet('form.post_code', 'PA03')
            ->assertSet('form.measure_type', 'Lead');
    }

    public function test_editing_an_approved_kpi_sends_it_back_to_finance(): void
    {
        $this->actingAsRole('Kepala Departemen');
        $this->seedExamples();
        $kpi = KpiCascade::where('code', 'SCM-H02')->first();

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->call('openEdit', $kpi->id)
            ->set('form.target', '7')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(KpiCascade::BELUM_DIUJI, $kpi->fresh()->validation_status);
        $this->assertSame(7.0, $kpi->fresh()->target);
    }

    public function test_only_finance_sets_the_validation_status(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedExamples(KpiCascade::BELUM_DIUJI);
        $kpi = KpiCascade::where('code', 'SCM-H02')->first();

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->call('openEdit', $kpi->id)
            ->set('form.validation_status', 'Lolos')
            ->set('form.finance_notes', 'Uji A 8 Ya')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(KpiCascade::LOLOS, $kpi->fresh()->validation_status);
        $this->assertSame('Uji A 8 Ya', $kpi->fresh()->finance_notes);
    }

    public function test_renaming_a_code_keeps_children_attached(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedExamples();
        $kpi = KpiCascade::where('code', 'SCM-H01')->first();

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->call('openEdit', $kpi->id)
            ->set('form.code', 'SCM-H10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, KpiCascade::where('parent_code', 'SCM-H10')->count());
    }

    public function test_a_parent_cannot_be_deleted_while_it_has_children(): void
    {
        $this->actingAsRole('Kepala Departemen');
        $this->seedExamples();

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->call('confirmDelete', KpiCascade::where('code', 'SCM-H01')->value('id'))
            ->call('delete');
        $this->assertTrue(KpiCascade::where('code', 'SCM-H01')->exists());

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->call('confirmDelete', KpiCascade::where('code', 'SCM-T02')->value('id'))
            ->call('delete');
        $this->assertFalse(KpiCascade::where('code', 'SCM-T02')->exists());
    }

    public function test_a_viewer_cannot_change_the_cascade(): void
    {
        $this->actingAsRole('Viewer');

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->call('openCreate', 'SCM')
            ->assertSet('showModal', false)
            ->set('form', ['code' => 'X-1'])
            ->call('save');

        $this->assertSame(0, KpiCascade::count());
    }

    /* ======================================================= monitoring */

    public function test_only_approved_kpis_enter_monitoring_and_actuals_are_kept(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedExamples();
        KpiCascade::where('code', 'CMP-H02')->update(['validation_status' => KpiCascade::REVISI]);
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);

        $komponen = Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->set('syncPeriod', '2026-08')
            ->call('syncToPeriod');

        $this->assertSame(17, DepartmentObjective::where('period', '2026-08')->count());
        $revenue = DepartmentObjective::where('kpi_code', 'TTC-H01')->firstOrFail();
        $this->assertSame('TTC', $revenue->dept_code);
        $this->assertEqualsWithDelta(162.4e9, (float) $revenue->target, 1); // tidak lagi terpotong decimal(10,2)
        $this->assertStringContainsString('Eyebost', $revenue->kpi_name);

        // Realisasi yang sudah diisi tetap; target baru ikut diperbarui.
        $revenue->update(['actual' => 150e9]);
        KpiCascade::where('code', 'TTC-H01')->update(['target' => 160e9]);
        $komponen->call('syncToPeriod');

        $revenue->refresh();
        $this->assertEqualsWithDelta(150e9, (float) $revenue->actual, 1);
        $this->assertEqualsWithDelta(160e9, (float) $revenue->target, 1);
        $this->assertSame(93.75, (float) $revenue->achievement_pct);
        $this->assertSame(17, DepartmentObjective::where('period', '2026-08')->count());
    }

    public function test_a_closed_period_is_not_synchronised(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedExamples();
        Period::create(['period' => '2026-08', 'status' => 'CLOSED', 'apex_score' => 0]);

        Livewire::test(KpiCascades::class, ['year' => '2026'])
            ->set('syncPeriod', '2026-08')
            ->call('syncToPeriod');

        $this->assertSame(0, DepartmentObjective::count());
    }

    public function test_objective_achievement_follows_polarity(): void
    {
        $this->actingAsRole('Admin FAT');
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
        $turun = DepartmentObjective::create(['period' => '2026-08', 'dept_code' => 'TTC', 'kpi_code' => 'TTC-H02', 'kpi_name' => 'Ad cost ratio', 'polarity' => 'Turun', 'target' => 0.2, 'actual' => 0]);
        $rentang = DepartmentObjective::create(['period' => '2026-08', 'dept_code' => 'FAT', 'kpi_code' => 'FAT-H01', 'kpi_name' => 'DPO', 'polarity' => 'Rentang', 'target' => 45, 'actual' => 0]);

        Livewire::test(DepartmentObjectives::class)
            ->call('editObjective', $turun->id)->set('editTarget', 0.2)->set('editActual', 0.25)->call('updateObjective')
            ->call('editObjective', $rentang->id)->set('editTarget', 45)->set('editActual', 54)->call('updateObjective');

        $this->assertSame(80.0, (float) $turun->fresh()->achievement_pct);
        // Rentang: 1 − |54 − 45| ÷ 45 = 80%, bukan 100% seperti cara lama (dibaca "Naik").
        $this->assertSame(80.0, (float) $rentang->fresh()->achievement_pct);
    }
}
