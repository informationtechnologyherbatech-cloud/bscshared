<?php

namespace Tests\Feature;

use App\Livewire\RevenueTargets;
use App\Models\Entity;
use App\Models\RevenuePlan;
use App\Models\RevenueTarget;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alur Target Revenue: isi target setahun disahkan → fasing → Simpan; dan menu
 * samping dikelompokkan per tingkat piramida.
 */
class RevenueFormFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::create([
            'name' => 'Finance', 'email' => 'fat@contoh.test', 'password' => bcrypt('x'), 'is_active' => true,
            'entity_id' => Entity::where('code', 'ERDIGMA')->value('id'),
        ]);
        $this->user->assignRole('Admin FAT');
        $this->actingAs($this->user);
    }

    public function test_phasing_uses_the_approved_target_and_saving_keeps_everything(): void
    {
        Livewire::test(RevenueTargets::class, ['year' => '2026'])
            ->set('approvedTarget', '1200000000')
            ->call('phaseEvenly')
            ->assertHasNoErrors()
            ->assertSet('rows.01.target', '100000000')
            ->call('save')
            ->assertHasNoErrors()
            // Setelah disimpan angka tetap tampil (dimuat ulang dari basis data).
            ->assertSet('approvedTarget', '1200000000')
            ->assertSet('rows.12.target', '100000000');

        $this->assertEqualsWithDelta(1200000000, RevenuePlan::where('year', '2026')->value('approved_target'), 0.01);
        $this->assertEqualsWithDelta(1200000000, (float) RevenueTarget::where('period', 'like', '2026-%')->sum('target'), 0.01);
    }

    public function test_a_revision_takes_precedence_when_phasing(): void
    {
        Livewire::test(RevenueTargets::class, ['year' => '2026'])
            ->set('approvedTarget', '1200000000')
            ->set('revisedTarget', '600000000')
            ->call('phaseEvenly')
            ->assertSet('rows.01.target', '50000000');
    }

    public function test_phasing_without_an_approved_target_explains_what_to_fill(): void
    {
        Livewire::test(RevenueTargets::class, ['year' => '2026'])
            ->call('phaseEvenly')
            ->assertHasErrors('annualTarget')
            ->assertSee('Disahkan direksi');
    }

    public function test_the_fields_are_not_marked_invalid_without_an_error(): void
    {
        $html = Livewire::test(RevenueTargets::class, ['year' => '2026'])->html();

        $this->assertStringNotContainsString('is-invalid', $html);
        $this->assertStringNotContainsString('@error', $html);
    }

    public function test_the_sidebar_is_grouped_by_pyramid_tier(): void
    {
        $halaman = $this->get(route('revenue'))->assertOk();

        $halaman->assertSeeInOrder([
            'RINGKASAN KINERJA', 'Piramida BSC',
            'TINGKAT 1 · REVENUE', 'Perencanaan Target', 'Target &amp; Realisasi',
            'TINGKAT 2 · RASIO KEUANGAN', 'Katalog Rasio', 'Pos Akun', 'Rasio Keuangan',
            'TINGKAT 3 · KPI &amp; SASARAN MUTU', 'Peta Pos Akun', 'Cascade KPI', 'Uji Indikator', 'Objective Departemen',
            'TINGKAT 4 · PROGRAM KERJA', 'Program Kerja (Action)',
        ], false);
    }

    public function test_every_admin_page_carries_the_save_feedback(): void
    {
        // Toast melayang + penanda tombol sedang diproses dimuat di layout admin.
        $this->get(route('revenue'))
            ->assertOk()
            ->assertSee('id="bsc-toasts"', false)
            ->assertSee('button[data-loading]', false)
            ->assertSee("hook('morphed'", false);
    }
}
