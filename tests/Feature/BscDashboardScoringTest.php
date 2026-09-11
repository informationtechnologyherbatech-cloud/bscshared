<?php

namespace Tests\Feature;

use App\Livewire\BscDashboard;
use App\Models\ActionPlan;
use App\Models\DepartmentObjective;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Support\ScoreStatus;
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

    private function objective(string $period, float $achievement): DepartmentObjective
    {
        return DepartmentObjective::create([
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
        $objective = $this->objective('2026-08', 60);
        // Ditautkan ke sasaran mutu periode ini; program kerja tanpa tautan tidak
        // berperiode sehingga tidak ikut diskor.
        ActionPlan::create([
            'department_objective_id' => $objective->id,
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

    public function test_the_apex_formula_label_reflects_the_weights_actually_used(): void
    {
        $this->period();
        $this->ratio('2026-08', 90);
        $this->objective('2026-08', 70);

        $html = Livewire::test(BscDashboard::class)->html();

        // Label lama menyebut rumus yang sudah tidak dipakai lagi.
        $this->assertStringNotContainsString('45% Revenue', $html);
        $this->assertStringContainsString('Rata-rata terbobot', $html);
        // Tanpa program kerja, bobot 45/35 dinormalisasi menjadi 56/44.
        $this->assertStringContainsString('56% Rasio Keuangan', $html);
        $this->assertStringContainsString('44% Sasaran Mutu', $html);
    }

    public function test_the_pyramid_keeps_its_shape_and_offers_short_labels_for_narrow_screens(): void
    {
        $this->period();
        $this->ratio('2026-08', 90);

        $html = Livewire::test(BscDashboard::class)->html();

        // Bentuk segitiga dipertahankan di semua ukuran layar.
        $this->assertStringContainsString('clip-path: polygon(50% 0%, 62.5% 100%, 37.5% 100%)', $html);
        $this->assertStringNotContainsString('clip-path: none', $html);

        // Label panjang untuk layar lebar, label pendek untuk layar sempit.
        $this->assertStringContainsString('Tingkat 2: Rasio Keuangan', $html);
        $this->assertStringContainsString('T2: Rasio Keuangan', $html);
        $this->assertStringContainsString('T4: Program Kerja', $html);

        // Piramida dibungkus wadah yang dapat digeser, supaya tidak terpotong.
        $this->assertStringContainsString('pyramid-scroll', $html);
    }

    /**
     * Program kerja tidak punya kolom periode; keterkaitannya lewat sasaran mutu
     * yang dimitigasinya. Tanpa penyaringan, periode lain ikut terhitung.
     */
    private function actionPlanFor(string $period, int $progress): ActionPlan
    {
        $objective = DepartmentObjective::create([
            'period' => $period,
            'dept_code' => 'PRO',
            'kpi_code' => 'KPI-'.$period.'-'.$progress,
            'kpi_name' => 'Sasaran mutu '.$period,
            'polarity' => 'Naik',
            'target' => 100,
            'actual' => 100,
            'achievement_pct' => 100,
            'status' => 'Tercapai',
        ]);

        return ActionPlan::create([
            'department_objective_id' => $objective->id,
            'title' => 'Program kerja '.$period,
            'owner_dept' => 'PRO',
            'progress_pct' => $progress,
            'status' => 'On Progress',
        ]);
    }

    public function test_tier_four_ignores_action_plans_from_other_periods(): void
    {
        $this->period('2026-08');
        $this->period('2026-07');
        $this->ratio('2026-08', 90);

        // Program kerja hanya ada pada periode lain.
        $this->actionPlanFor('2026-07', 60);

        $html = Livewire::test(BscDashboard::class)
            ->set('selectedPeriod', '2026-08')
            ->html();

        // Periode terpilih memang belum punya program kerja.
        $this->assertStringContainsString('data belum lengkap', $html);
        $this->assertStringNotContainsString('60.0%', $html);
    }

    public function test_tier_four_counts_only_the_selected_period(): void
    {
        $this->period('2026-08');
        $this->period('2026-07');

        $this->actionPlanFor('2026-08', 40);
        $this->actionPlanFor('2026-07', 100);

        $viewData = Livewire::test(BscDashboard::class)
            ->set('selectedPeriod', '2026-08')
            ->viewData('avgActionProgress');

        // Tanpa penyaringan, rata-ratanya menjadi 70 karena periode lain ikut.
        $this->assertSame(40.0, (float) $viewData);
    }

    public function test_an_action_plan_without_an_objective_is_not_attributed_to_any_period(): void
    {
        $this->period('2026-08');
        $this->ratio('2026-08', 90);

        ActionPlan::create([
            'department_objective_id' => null,
            'title' => 'Program kerja lepas',
            'owner_dept' => 'PRO',
            'progress_pct' => 100,
            'status' => 'On Progress',
        ]);

        $html = Livewire::test(BscDashboard::class)
            ->set('selectedPeriod', '2026-08')
            ->html();

        $this->assertStringContainsString('data belum lengkap', $html);
    }

    public function test_each_tier_carries_a_status_dot_matching_its_score(): void
    {
        $this->period();
        $this->ratio('2026-08', 100);   // tercapai  -> hijau
        $this->objective('2026-08', 90); // waspada  -> oranye
        // Program kerja sengaja dikosongkan -> abu-abu.

        $html = Livewire::test(BscDashboard::class)->html();

        $this->assertStringContainsString(ScoreStatus::color(ScoreStatus::TERCAPAI), $html);
        $this->assertStringContainsString(ScoreStatus::color(ScoreStatus::WASPADA), $html);
        $this->assertStringContainsString(ScoreStatus::color(ScoreStatus::BELUM_LENGKAP), $html);
        $this->assertSame(4, substr_count($html, 'class="tier-status-dot"'));
    }

    public function test_a_tier_without_data_says_so_instead_of_showing_zero(): void
    {
        $this->period();
        $this->ratio('2026-08', 90);

        $html = Livewire::test(BscDashboard::class)->html();

        // Tanpa program kerja, "0,0%" akan terbaca sebagai capaian nol.
        $this->assertStringContainsString('data belum lengkap', $html);
    }

    public function test_the_legend_lists_every_status_exactly_once(): void
    {
        $this->period();
        $this->ratio('2026-08', 90);

        $html = Livewire::test(BscDashboard::class)->html();

        foreach (ScoreStatus::legend() as $status) {
            // Label memuat "<80%" yang di-escape Blade menjadi "&lt;80%".
            $this->assertStringContainsString(e($status['label']), $html);
        }

        $this->assertSame(
            count(ScoreStatus::legend()),
            substr_count($html, 'class="pyramid-legend-dot"')
        );
    }

    public function test_the_pyramid_labels_are_not_hardcoded_numbers(): void
    {
        $this->period();
        $this->ratio('2026-08', 90);
        $this->ratio('2026-08', 80);

        $html = Livewire::test(BscDashboard::class)->html();

        $this->assertStringNotContainsString('7 Rasio Utama', $html);
        $this->assertStringContainsString('2 Rasio Keuangan', $html);
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
