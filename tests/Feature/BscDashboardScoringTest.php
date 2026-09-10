<?php

namespace Tests\Feature;

use App\Livewire\BscDashboard;
use App\Models\ActionPlan;
use App\Models\DepartmentObjective;
use App\Models\FinancialRatio;
use App\Models\Period;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BscDashboardScoringTest extends TestCase
{
    use RefreshDatabase;

    private function period(string $period = '2026-08'): Period
    {
        return Period::create([
            'period' => $period,
            'status' => 'OPEN',
            'apex_score' => 0,
        ]);
    }

    private function ratio(string $period, float $achievement): void
    {
        FinancialRatio::create([
            'period' => $period,
            'category' => 'Likuiditas',
            'ratio_name' => 'Current Ratio '.$achievement,
            'target' => 2,
            'actual' => 2,
            'achievement_pct' => $achievement,
            'status' => 'Tercapai',
        ]);
    }

    private function objective(string $period, float $achievement): void
    {
        DepartmentObjective::create([
            'period' => $period,
            'dept_code' => 'QC',
            'kpi_code' => 'KPI-'.$achievement,
            'kpi_name' => 'Sasaran mutu uji',
            'polarity' => 'Naik',
            'target' => 100,
            'actual' => $achievement,
            'achievement_pct' => $achievement,
            'status' => 'Waspada',
        ]);
    }

    public function test_apex_score_is_a_weighted_average_of_the_three_tiers(): void
    {
        $this->period();
        $this->ratio('2026-08', 80);
        $this->objective('2026-08', 60);
        ActionPlan::create([
            'title' => 'Perbaikan lini produksi',
            'owner_dept' => 'PRO',
            'progress_pct' => 50,
            'status' => 'On Progress',
        ]);

        // 0.45*80 + 0.35*60 + 0.20*50 = 67.00
        Livewire::test(BscDashboard::class)->assertViewHas('apexScore', 67.0);
    }

    public function test_apex_score_renormalises_when_a_tier_has_no_data(): void
    {
        $this->period();
        $this->ratio('2026-08', 80);

        // Hanya rasio yang terisi, jadi skornya persis rata-rata rasio.
        Livewire::test(BscDashboard::class)->assertViewHas('apexScore', 80.0);
    }

    public function test_apex_score_is_zero_when_the_period_has_no_data_at_all(): void
    {
        $this->period();

        // Rumus lama menghasilkan 43.2 (0.45 x konstanta 96) walau tanpa data.
        Livewire::test(BscDashboard::class)->assertViewHas('apexScore', 0.0);
    }

    public function test_apex_score_is_persisted_to_the_period(): void
    {
        $period = $this->period();
        $this->ratio('2026-08', 90);

        Livewire::test(BscDashboard::class);

        $this->assertSame(90.0, (float) $period->fresh()->apex_score);
    }

    public function test_apex_score_is_not_persisted_for_a_closed_period(): void
    {
        $period = $this->period();
        $period->update(['status' => 'CLOSED']);
        $this->ratio('2026-08', 90);

        Livewire::test(BscDashboard::class);

        $this->assertSame(0.0, (float) $period->fresh()->apex_score);
    }

    public function test_stale_data_warning_lights_up_after_the_configured_threshold(): void
    {
        $period = $this->period();
        DB::table('periods')->where('id', $period->id)->update([
            'updated_at' => now()->subHours(30),
        ]);

        Livewire::test(BscDashboard::class)
            ->assertViewHas('isStale', true)
            ->assertViewHas('hoursSinceSync', fn ($hours) => $hours >= 30);
    }

    public function test_recalculating_the_score_does_not_reset_the_stale_warning(): void
    {
        $period = $this->period();
        $this->ratio('2026-08', 90);
        DB::table('periods')->where('id', $period->id)->update([
            'updated_at' => now()->subHours(30),
        ]);

        // Render pertama menulis apex_score; penulisan itu tidak boleh
        // memperbarui updated_at, karena kolom itu menandai sinkronisasi data.
        Livewire::test(BscDashboard::class)->assertViewHas('isStale', true);
        Livewire::test(BscDashboard::class)->assertViewHas('isStale', true);

        $this->assertSame(90.0, (float) $period->fresh()->apex_score);
    }

    public function test_fresh_data_does_not_raise_the_stale_warning(): void
    {
        $period = $this->period();
        DB::table('periods')->where('id', $period->id)->update([
            'updated_at' => now()->subHours(2),
        ]);

        Livewire::test(BscDashboard::class)->assertViewHas('isStale', false);
    }
}
