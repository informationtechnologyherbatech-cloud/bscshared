<?php

namespace Tests\Feature;

use App\Livewire\MethodDocumentation;
use App\Models\ActionPlan;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\KpiCascade;
use App\Models\Period;
use App\Models\RatioDefinition;
use App\Models\User;
use App\Models\WorkUnit;
use App\Support\Bsc\MethodSelfTest;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Menu Dokumentasi Metode (panduan, metode, uji mandiri) dan seeder tanpa data
 * capaian secara bawaan.
 */
class MethodDocumentationTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-doc@contoh.test', 'password' => bcrypt('x'), 'is_active' => true,
            'entity_id' => Entity::where('code', 'ERDIGMA')->value('id'),
        ]);
        $user->assignRole('Super Admin');
        $this->actingAs($user);
    }

    public function test_the_self_test_passes_all_twelve_checks(): void
    {
        $hasil = MethodSelfTest::run();

        $this->assertCount(12, $hasil);
        foreach ($hasil as $t) {
            $this->assertTrue($t['ok'], "Uji mandiri #{$t['no']} {$t['name']}: harap {$t['expected']}, dapat {$t['actual']}");
        }
    }

    public function test_the_documentation_page_shows_guide_method_and_self_test(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->login();

        $this->get(route('dokumentasi'))->assertOk()
            ->assertSee('Panduan Pengisian Super Apps BSC')
            ->assertSee('Tingkat 2 · Rasio Keuangan', false)
            ->assertDontSee('Segera Hadir');

        Livewire::test(MethodDocumentation::class)
            ->call('switchTab', 'metode')
            ->assertSee('Inventory Turnover')
            ->assertSee('target ÷ aktual')
            ->call('switchTab', 'uji')
            ->call('runSelfTest')
            ->assertSee('12 dari 12');
    }

    public function test_seeding_without_demo_prepares_structure_only(): void
    {
        config(['bsc.seed_demo' => false]);

        $this->seed(DatabaseSeeder::class);
        app(EntityContext::class)->use(Entity::where('code', 'ERDIGMA')->value('id'));

        // Struktur siap…
        $this->assertSame(17, WorkUnit::count());
        $this->assertSame(19, RatioDefinition::count());
        $this->assertTrue(User::where('email', 'superadmin@emc.co.id')->exists());
        // …tanpa angka capaian: semua diisi sendiri lewat menu.
        $this->assertSame(0, Period::count());
        $this->assertSame(0, FinancialRatio::count());
        $this->assertSame(0, DepartmentObjective::count());
        $this->assertSame(0, KpiCascade::count());
        $this->assertSame(0, ActionPlan::count());
    }
}
