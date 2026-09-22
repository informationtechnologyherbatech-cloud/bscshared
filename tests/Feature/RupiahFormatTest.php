<?php

namespace Tests\Feature;

use App\Livewire\RevenueTargets;
use App\Models\Entity;
use App\Models\RevenueTarget;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nilai uang ditampilkan & diketik dalam format rupiah Indonesia
 * ("Rp 1.000.000.000"), sementara yang tersimpan tetap angka murni.
 */
class RupiahFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::create([
            'name' => 'Finance', 'email' => 'fat@contoh.test', 'password' => bcrypt('x'), 'is_active' => true,
            'entity_id' => Entity::where('code', 'ERDIGMA')->value('id'),
        ]);
        $user->assignRole('Admin FAT');
        $this->actingAs($user);
    }

    public function test_the_rupiah_helper_uses_indonesian_separators(): void
    {
        $this->assertSame('Rp 1.000.000.000', rupiah(1000000000));
        $this->assertSame('Rp 1.000.000,50', rupiah('1000000.5', 2));
        $this->assertSame('-Rp 2.500', rupiah(-2500));
        $this->assertSame('—', rupiah(null));
        $this->assertSame('—', rupiah(''));
    }

    public function test_the_input_component_binds_the_raw_model_on_its_wrapper(): void
    {
        $html = Blade::render('<x-input-rupiah wire:model.live.debounce.500ms="rows.01.target" class="form-control form-control-sm text-right" placeholder="isi" />');

        // wire:model (beserta modifier) di pembungkus x-modelable; atribut lain di input teks.
        $this->assertStringContainsString('x-modelable="nilai" wire:model.live.debounce.500ms="rows.01.target"', $html);
        $this->assertStringContainsString('x-data="inputRupiah(\'Rp\', 2)"', $html);
        $this->assertMatchesRegularExpression('/<input type="text" inputmode="decimal"[^>]*class="form-control form-control-sm text-right"[^>]*placeholder="isi"/s', $html);
        $this->assertStringNotContainsString('type="number"', $html);
    }

    public function test_a_non_currency_count_can_drop_the_prefix(): void
    {
        $html = Blade::render('<x-input-rupiah wire:model="values.PA15.amount" prefix="" :decimals="0" />');

        $this->assertStringContainsString('x-data="inputRupiah(\'\', 0)"', $html);
    }

    public function test_the_revenue_page_uses_rupiah_inputs_and_totals(): void
    {
        RevenueTarget::create(['period' => '2026-01', 'target' => 1000000000, 'actual' => 950000000]);

        Livewire::test(RevenueTargets::class, ['year' => '2026'])
            ->assertSeeHtml('wire:model.live.debounce.500ms="rows.01.target"')
            ->assertSeeHtml('x-modelable="nilai"')
            ->assertSee('Rp 1.000.000.000')
            ->assertSee('Rp 950.000.000');
    }

    public function test_the_annual_target_accepts_raw_or_formatted_text(): void
    {
        // Angka murni dari komponen rupiah…
        $rows = Livewire::test(RevenueTargets::class, ['year' => '2027'])
            ->set('annualTarget', '1200000000')
            ->call('phaseEvenly')
            ->get('rows');
        $this->assertEqualsWithDelta(100000000, (float) $rows['01']['target'], 0.01);

        // …maupun teks berformat yang ditempel.
        $rows = Livewire::test(RevenueTargets::class, ['year' => '2027'])
            ->set('annualTarget', 'Rp 1.200.000.000')
            ->call('phaseEvenly')
            ->get('rows');
        $this->assertEqualsWithDelta(100000000, (float) $rows['01']['target'], 0.01);
    }
}
