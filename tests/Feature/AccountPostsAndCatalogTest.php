<?php

namespace Tests\Feature;

use App\Livewire\AccountBalances;
use App\Livewire\BscDashboard;
use App\Livewire\FinancialRatios;
use App\Livewire\RatioCatalog;
use App\Models\AccountBalance;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\RatioDefinition;
use App\Models\RatioTarget;
use App\Models\User;
use App\Support\Bsc\RatioEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Layar Tingkat 2: Pos Akun, Katalog Rasio, dan dampaknya ke Rasio Keuangan
 * serta piramida. Angka masukan sama dengan RatioEngineWorkbookTest (F2 = 94,1).
 */
class AccountPostsAndCatalogTest extends TestCase
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

    private function seedTargets(string $year = '2026'): void
    {
        foreach (self::TARGET as $kode => $target) {
            RatioTarget::create(['year' => $year, 'code' => $kode, 'target' => $target]);
        }
    }

    private function fillPosts($komponen)
    {
        foreach (self::POS_AKUN as $kode => [$nilai, $awal]) {
            $komponen->set("values.$kode.amount", (string) $nilai);
            if ($awal !== null) {
                $komponen->set("values.$kode.opening", (string) $awal);
            }
        }

        return $komponen;
    }

    /* ============================================================ POS AKUN */

    public function test_saving_account_posts_computes_the_ratios(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedTargets();

        $this->fillPosts(Livewire::test(AccountBalances::class, ['period' => '2026-08']))
            ->assertViewHas('hasil', fn ($h) => $h['f2'] === 94.1) // pratinjau sebelum simpan
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(16, AccountBalance::where('period', '2026-08')->count());
        $this->assertSame($this->erdigma->id, AccountBalance::first()->entity_id);
        $this->assertSame(19, FinancialRatio::where('period', '2026-08')->where('source', RatioEngine::SOURCE_COMPUTED)->count());
        $this->assertSame(94.1, RatioEngine::storedScore('2026-08'));
    }

    public function test_the_opening_balance_is_carried_to_later_months_of_the_same_year(): void
    {
        $this->actingAsRole('Admin FAT');
        AccountBalance::create(['period' => '2026-07', 'code' => 'PA05', 'amount' => 115e9, 'opening' => 108e9]);

        Livewire::test(AccountBalances::class, ['period' => '2026-08'])
            ->assertSet('values.PA05.opening', '108000000000')
            ->assertSet('values.PA05.amount', '');
    }

    public function test_a_viewer_cannot_save_account_posts(): void
    {
        $this->actingAsRole('Kepala Departemen');

        Livewire::test(AccountBalances::class, ['period' => '2026-08'])
            ->set('values.PA01.amount', '540000000000')
            ->call('save');

        $this->assertSame(0, AccountBalance::count());
    }

    public function test_a_closed_period_cannot_be_changed(): void
    {
        $this->actingAsRole('Admin FAT');
        Period::create(['period' => '2026-08', 'status' => 'CLOSED', 'apex_score' => 0]);

        Livewire::test(AccountBalances::class, ['period' => '2026-08'])
            ->set('values.PA01.amount', '540000000000')
            ->call('save');

        $this->assertSame(0, AccountBalance::count());
    }

    public function test_clearing_a_post_removes_it_and_its_dependent_ratios(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedTargets();

        $komponen = $this->fillPosts(Livewire::test(AccountBalances::class, ['period' => '2026-08']))->call('save');

        // Tanpa jumlah karyawan, D1 tidak dapat dihitung lagi.
        $komponen->set('values.PA15.amount', '')->call('save');

        $this->assertFalse(AccountBalance::where('code', 'PA15')->exists());
        $this->assertFalse(FinancialRatio::where('ratio_code', 'D1')->exists());
        $this->assertSame(18, FinancialRatio::where('source', RatioEngine::SOURCE_COMPUTED)->count());
    }

    /* ======================================================= KATALOG RASIO */

    public function test_the_catalog_saves_weights_targets_and_recomputes_the_year(): void
    {
        $this->actingAsRole('Admin FAT');
        foreach (self::POS_AKUN as $kode => [$nilai, $awal]) {
            AccountBalance::create(['period' => '2026-08', 'code' => $kode, 'amount' => $nilai, 'opening' => $awal]);
        }

        $komponen = Livewire::test(RatioCatalog::class, ['year' => '2026']);
        foreach (self::TARGET as $kode => $target) {
            $komponen->set("rows.$kode.target", (string) $target);
        }
        $komponen->call('save')->assertHasNoErrors();

        $this->assertSame(19, RatioTarget::where('year', '2026')->count());
        $this->assertSame(94.1, RatioEngine::storedScore('2026-08'));

        // Nonaktifkan A1 — periode 2026-08 langsung dihitung ulang.
        $komponen->set('rows.A1.active', false)->call('save');

        $this->assertFalse(RatioDefinition::where('code', 'A1')->value('is_active'));
        $this->assertSame(round((94.1 - 4.2) / 94 * 100, 2), RatioEngine::storedScore('2026-08'));
    }

    public function test_the_catalog_reports_group_weights_and_consistency(): void
    {
        $this->actingAsRole('Admin FAT');

        $komponen = Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->assertViewHas('totalWeight', 100.0)
            ->assertViewHas('groups', fn ($g) => $g['Profitabilitas']['weight'] === 30.0 && $g['Solvabilitas']['weight'] === 10.0);

        $komponen->set('rows.P1.target', '37')->set('rows.P2.target', '40')
            ->assertViewHas('checks', fn ($c) => collect($c)->firstWhere('label', 'NPM target ≤ GPM target')['ok'] === false);
    }

    public function test_catalog_changes_are_limited_to_the_active_entity(): void
    {
        $this->actingAsRole('Admin FAT');

        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->set('rows.S2.active', false)
            ->call('save');

        $herbatech = Entity::where('code', 'HERBATECH')->value('id');
        $this->assertTrue((bool) RatioDefinition::withoutGlobalScopes()->where('entity_id', $herbatech)->where('code', 'S2')->value('is_active'));
        $this->assertFalse((bool) RatioDefinition::withoutGlobalScopes()->where('entity_id', $this->erdigma->id)->where('code', 'S2')->value('is_active'));
    }

    public function test_a_viewer_cannot_change_the_catalog(): void
    {
        $this->actingAsRole('Kepala Departemen');

        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->set('rows.P1.weight', '50')
            ->call('save');

        $this->assertSame(10.0, RatioDefinition::where('code', 'P1')->value('weight'));
    }

    /* ============================================= RASIO KEUANGAN & PIRAMIDA */

    public function test_a_computed_ratio_cannot_be_edited_directly(): void
    {
        $this->actingAsRole('Admin FAT');
        $this->seedTargets();
        foreach (self::POS_AKUN as $kode => [$nilai, $awal]) {
            AccountBalance::create(['period' => '2026-08', 'code' => $kode, 'amount' => $nilai, 'opening' => $awal]);
        }
        app(RatioEngine::class)->materialize('2026-08');
        $gpm = FinancialRatio::where('ratio_code', 'P1')->firstOrFail();

        Livewire::test(FinancialRatios::class)
            ->call('editRatio', $gpm->id)
            ->assertSet('editingRatioId', null)
            ->set('editingRatioId', $gpm->id)
            ->set('editActual', 99)
            ->call('updateRatio');

        $this->assertEqualsWithDelta(35.0, (float) $gpm->fresh()->actual, 1e-6);
    }

    public function test_the_pyramid_uses_the_rubric_score_for_tier_two(): void
    {
        $this->actingAsRole('Super Admin');
        $this->seedTargets();
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
        foreach (self::POS_AKUN as $kode => [$nilai, $awal]) {
            AccountBalance::create(['period' => '2026-08', 'code' => $kode, 'amount' => $nilai, 'opening' => $awal]);
        }
        app(RatioEngine::class)->materialize('2026-08');

        // Tanpa target revenue, F2 memikul seluruh bobot.
        Livewire::test(BscDashboard::class, ['selectedPeriod' => '2026-08'])
            ->assertViewHas('avgRatioScore', 94.1)
            ->assertViewHas('apexScore', 94.1);
    }

    public function test_computed_ratios_without_targets_leave_tier_two_incomplete(): void
    {
        $this->actingAsRole('Super Admin');
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
        foreach (self::POS_AKUN as $kode => [$nilai, $awal]) {
            AccountBalance::create(['period' => '2026-08', 'code' => $kode, 'amount' => $nilai, 'opening' => $awal]);
        }
        app(RatioEngine::class)->materialize('2026-08');

        Livewire::test(BscDashboard::class, ['selectedPeriod' => '2026-08'])
            ->assertViewHas('apexScore', 0.0);
    }
}
