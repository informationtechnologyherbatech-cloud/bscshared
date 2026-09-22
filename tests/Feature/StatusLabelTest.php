<?php

namespace Tests\Feature;

use App\Livewire\BscDashboard;
use App\Livewire\FinancialRatios;
use App\Models\Entity;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/** Label status seragam di semua tabel; tidak ada lagi "Off-Target". */
class StatusLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-erdigma@contoh.test', 'password' => bcrypt('x'), 'is_active' => true,
            'entity_id' => Entity::where('code', 'ERDIGMA')->value('id'),
        ]);
        $user->assignRole('Super Admin');
        $this->actingAs($user);
    }

    public function test_the_badge_maps_every_status_including_legacy_values(): void
    {
        $label = fn (?string $s) => trim(strip_tags(Blade::render('<x-status-badge :status="$s" />', ['s' => $s])));

        $this->assertSame('Tercapai', $label('Tercapai'));
        $this->assertSame('Waspada', $label('Waspada'));
        $this->assertSame('Di Bawah Target', $label('Di Bawah Target'));
        $this->assertSame('Di Bawah Target', $label('Off-Target'));
        $this->assertSame('Belum Ada Target', $label('Belum Ada Target'));
    }

    public function test_tables_no_longer_say_off_target(): void
    {
        // Data contoh: A1 & A4 berstatus Di Bawah Target.
        Livewire::test(BscDashboard::class)
            ->call('selectLevel', 2)
            ->assertSee('Di Bawah Target')
            ->assertDontSee('Off-Target');

        Livewire::test(FinancialRatios::class)->assertDontSee('Off-Target');
    }

    public function test_tier_one_offers_a_way_to_fill_revenue(): void
    {
        Livewire::test(BscDashboard::class)
            ->call('selectLevel', 1)
            ->assertSee('Isi target & realisasi bulanan')
            ->assertSeeHtml(route('revenue', ['year' => '2026']));
    }
}
