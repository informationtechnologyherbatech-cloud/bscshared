<?php

namespace Tests\Feature;

use App\Livewire\BscDashboard;
use App\Livewire\DepartmentObjectives;
use App\Livewire\FinancialRatios;
use App\Livewire\RatioCatalog;
use App\Livewire\RevenuePlanning;
use App\Models\Entity;
use App\Models\Period;
use App\Models\User;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Periode di navbar berlaku di semua halaman: bawaannya bulan berjalan, dipilih
 * sekali lalu diikuti halaman lain, dan memilih periode di halaman ikut mengubahnya.
 */
class ActivePeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-22 10:00:00');
        $this->seed(DatabaseSeeder::class); // periode contoh 2026-08
        $erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-periode@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => $erdigma->id,
        ]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
        app(EntityContext::class)->use($erdigma->id);

        foreach (['2026-07', '2026-09', '2026-10'] as $p) {
            Period::create(['period' => $p, 'status' => 'OPEN', 'apex_score' => 0]);
        }
    }

    public function test_the_default_is_the_current_month(): void
    {
        $this->assertSame('2026-09', Period::active()); // bukan 2026-10 yang lebih baru
    }

    public function test_without_the_current_month_the_last_earlier_period_is_used(): void
    {
        Period::where('period', '2026-09')->delete();

        $this->assertSame('2026-08', Period::active());
        $this->assertSame('2026-10', Period::defaultFrom(['2026-10'])); // hanya ada periode mendatang
    }

    public function test_choosing_a_period_in_the_navbar_applies_to_every_page(): void
    {
        $this->from('/objectives?period=2026-09&status=Tercapai')
            ->post(route('period.switch'), ['period' => '2026-08'])
            ->assertRedirect('/objectives?status=Tercapai'); // periode lama di URL dibuang

        $this->assertSame('2026-08', Period::active());
        Livewire::test(BscDashboard::class)->assertSet('selectedPeriod', '2026-08');
        Livewire::test(FinancialRatios::class)->assertSet('selectedPeriod', '2026-08');
        Livewire::test(DepartmentObjectives::class)->assertSet('selectedPeriod', '2026-08');
        Livewire::test(RatioCatalog::class)->assertSet('year', '2026');
        Livewire::test(RevenuePlanning::class)->assertSet('year', '2027');

        $this->get('/')->assertSee('data-periode-aktif>2026-08', false);
    }

    public function test_a_period_that_does_not_exist_is_refused(): void
    {
        $this->from('/')->post(route('period.switch'), ['period' => '2030-01'])->assertRedirect('/');

        $this->assertSame('2026-09', Period::active());
    }

    public function test_choosing_a_period_on_a_page_updates_the_navbar_period(): void
    {
        Livewire::test(DepartmentObjectives::class)
            ->set('selectedPeriod', '2026-07')
            ->assertDispatched('periode-aktif', period: '2026-07');

        $this->assertSame('2026-07', Period::active());
        Livewire::test(BscDashboard::class)->assertSet('selectedPeriod', '2026-07');
    }

    public function test_a_period_link_from_another_page_becomes_the_active_period(): void
    {
        Livewire::withQueryParams(['period' => '2026-07'])
            ->test(FinancialRatios::class)
            ->assertSet('selectedPeriod', '2026-07');

        $this->assertSame('2026-07', Period::active());
    }

    public function test_the_drill_down_link_opens_the_full_ratio_list(): void
    {
        Livewire::test(BscDashboard::class)
            ->set('selectedPeriod', '2026-08')
            ->call('selectLevel', 2)
            ->assertSee('Buka menu Rasio Keuangan')
            ->assertDontSee('Utuh')
            ->assertDontSee('status=all', false);

        // Tautan lama dengan status=all tidak lagi menyaring semuanya habis.
        Livewire::withQueryParams(['status' => 'all', 'period' => '2026-08'])
            ->test(FinancialRatios::class)
            ->assertSet('selectedStatus', '')
            ->assertViewHas('ratios', fn ($r) => $r->count() === 19);
    }

    public function test_each_entity_keeps_its_own_active_period(): void
    {
        Period::setActive('2026-07');
        app(EntityContext::class)->use(Entity::where('code', 'AEJ')->value('id'));

        $this->assertSame('2026-09', Period::defaultFrom(['2026-09']));
        $this->assertNotSame('2026-07', Period::active()); // AEJ tidak punya periode 2026-07
    }
}
