<?php

namespace Tests\Feature;

use App\Livewire\AccountPostMap;
use App\Livewire\WorkUnits;
use App\Models\AccountPostRole;
use App\Models\Entity;
use App\Models\User;
use App\Models\WorkUnit;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\PostMap;
use App\Support\Bsc\RatioLibrary;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sheet "Peta Rasio-Akun-Dept" harus tereproduksi persis: bagian 1 (rasio ×
 * pos akun), bagian 2 Erdigma (unit × pos akun), dan bagian 3 (rasio yang
 * boleh diklaim tiap unit, baris "Σ rasio yang digerakkan tiap unit").
 */
class PostMapWorkbookTest extends TestCase
{
    use RefreshDatabase;

    private Entity $erdigma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        app(EntityContext::class)->use($this->erdigma->id);
    }

    public function test_part_one_links_every_ratio_to_its_posts(): void
    {
        $sambungan = collect(RatioLibrary::posts())->sum(fn ($p) => count($p));
        $this->assertSame(47, $sambungan); // "Total sambungan"
        $this->assertSame(array_keys(RatioLibrary::all()), array_keys(RatioLibrary::posts()));

        // Bagian 1b kolom "Jml rasio" tiap pos akun.
        $harapan = [
            'PA01' => 11, 'PA02' => 8, 'PA03' => 3, 'PA04' => 3, 'PA05' => 3, 'PA06' => 2, 'PA07' => 1, 'PA08' => 1,
            'PA09' => 2, 'PA10' => 3, 'PA11' => 3, 'PA12' => 2, 'PA13' => 2, 'PA14' => 1, 'PA15' => 1, 'PA16' => 1,
        ];
        foreach ($harapan as $pos => $jumlah) {
            $nyata = collect(RatioLibrary::posts())->filter(fn ($p) => isset($p[$pos]))->count();
            $this->assertSame($jumlah, $nyata, "Jumlah rasio untuk {$pos} berbeda dari workbook.");
        }
    }

    public function test_part_three_matches_the_workbook_for_every_erdigma_unit(): void
    {
        $peta = new PostMap;

        // Baris 74: Σ rasio yang digerakkan tiap unit.
        $harapan = [
            'MFG' => 9, 'PDV' => 15, 'SCM' => 18, 'CMP' => 11, 'OFD' => 15, 'PTN' => 11, 'SOC' => 14, 'TTC' => 14,
            'ECO' => 15, 'CXP' => 11, 'BMK' => 12, 'FAT' => 13, 'LGL' => 11, 'HRG' => 7, 'ERS' => 5, 'SEC' => 3, 'DIT' => 5,
        ];
        foreach ($harapan as $unit => $jumlah) {
            $this->assertCount($jumlah, $peta->claimableRatios($unit), "Rasio yang boleh diklaim {$unit} berbeda dari workbook.");
        }

        // Contoh baris: MFG hanya lewat HPP & Persediaan; S1 hanya bisa diklaim FAT.
        $this->assertSame(['P1', 'P2', 'P3', 'P4', 'A1', 'A4', 'A6', 'D4', 'L2'], $peta->claimableRatios('MFG'));
        $this->assertSame(['FAT'], collect(array_keys($harapan))->filter(fn ($u) => $peta->canClaim($u, 'S1'))->values()->all());
    }

    public function test_every_erdigma_post_has_exactly_one_owner_except_sales(): void
    {
        $cek = (new PostMap)->ownerChecks();

        foreach ($cek as $pos => $c) {
            $this->assertTrue($c['ok'], "{$pos}: {$c['note']}");
        }
        $this->assertEqualsCanonicalizing(['OFD', 'PTN', 'SOC', 'TTC', 'ECO'], $cek['PA01']['owners']);
        $this->assertSame(['FAT'], $cek['PA03']['owners']);
        $this->assertSame(['HRG'], $cek['PA04']['owners']);
    }

    public function test_a_second_owner_or_a_missing_owner_is_flagged(): void
    {
        $peta = new PostMap(['SCM' => ['PA05' => 'O'], 'FAT' => ['PA05' => 'O']]);
        $cek = $peta->ownerChecks();

        $this->assertFalse($cek['PA05']['ok']);
        $this->assertStringContainsString('Ganda', $cek['PA05']['note']);
        $this->assertFalse($cek['PA02']['ok']);
        $this->assertSame('Perlu ditetapkan', $cek['PA02']['note']);
    }

    public function test_manufacturing_entities_start_without_a_map(): void
    {
        $herbatech = Entity::where('code', 'HERBATECH')->value('id');
        app(EntityContext::class)->use($herbatech);

        $this->assertSame(0, AccountPostRole::count());
        $this->assertSame(count(AccountPosts::all()), collect((new PostMap)->ownerChecks())->reject(fn ($c) => $c['ok'])->count());
    }

    public function test_finance_can_change_the_map_and_viewers_cannot(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fat = User::create([
            'name' => 'Finance', 'email' => 'fat@contoh.test', 'password' => bcrypt('Herbatech#2026aman'),
            'is_active' => true, 'entity_id' => $this->erdigma->id,
        ]);
        $fat->assignRole('Admin FAT');
        $this->actingAs($fat);

        Livewire::test(AccountPostMap::class)
            ->set('cells.DIT.PA11', 'O')
            ->set('cells.FAT.PA11', 'K')
            ->set('cells.SEC.PA03', '')
            ->call('save');

        $this->assertSame('O', AccountPostRole::where('unit_code', 'DIT')->where('post_code', 'PA11')->value('role'));
        $this->assertSame('K', AccountPostRole::where('unit_code', 'FAT')->where('post_code', 'PA11')->value('role'));
        $this->assertFalse(AccountPostRole::where('unit_code', 'SEC')->exists());

        $kadep = User::create([
            'name' => 'Kadep', 'email' => 'kadep@contoh.test', 'password' => bcrypt('Herbatech#2026aman'),
            'is_active' => true, 'entity_id' => $this->erdigma->id,
        ]);
        $kadep->assignRole('Kepala Departemen');
        $this->actingAs($kadep);

        Livewire::test(AccountPostMap::class)->set('cells.DIT.PA11', '')->call('save');
        $this->assertSame('O', AccountPostRole::where('unit_code', 'DIT')->where('post_code', 'PA11')->value('role'));
    }

    public function test_map_roles_follow_a_renamed_or_deleted_unit(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@contoh.test', 'password' => bcrypt('Herbatech#2026aman'),
            'is_active' => true, 'entity_id' => $this->erdigma->id,
        ]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        $dit = WorkUnit::where('code', 'DIT')->firstOrFail();
        Livewire::test(WorkUnits::class)
            ->call('openEdit', $dit->id)
            ->set('code', 'DAT')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, AccountPostRole::where('unit_code', 'DAT')->count());
        $this->assertFalse(AccountPostRole::where('unit_code', 'DIT')->exists());

        Livewire::test(WorkUnits::class)
            ->call('confirmDelete', $dit->id)
            ->call('delete');

        $this->assertNull($dit->fresh());
        $this->assertFalse(AccountPostRole::where('unit_code', 'DAT')->exists());
    }

    public function test_the_map_belongs_to_the_active_entity(): void
    {
        $aej = Entity::where('code', 'AEJ')->value('id');
        app(EntityContext::class)->use($aej);

        AccountPostRole::create(['unit_code' => 'FIN', 'post_code' => 'PA06', 'role' => 'O']);

        $this->assertSame(1, AccountPostRole::count());
        $this->assertSame($aej, AccountPostRole::first()->entity_id);

        app(EntityContext::class)->use($this->erdigma->id);
        $this->assertFalse(AccountPostRole::where('unit_code', 'FIN')->exists());
    }
}
