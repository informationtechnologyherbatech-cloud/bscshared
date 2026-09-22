<?php

namespace Tests\Feature;

use App\Livewire\BscDashboard;
use App\Models\ActionPlan;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\RevenueTarget;
use App\Support\EntityContext;
use App\Support\ScoreStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BscDashboardScoringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seluruh data BSC milik satu entitas. Tes ini tidak login, jadi entitasnya
     * ditetapkan langsung — meniru pengguna yang sedang membuka satu entitas.
     */
    protected function setUp(): void
    {
        parent::setUp();

        app(EntityContext::class)->use(Entity::where('code', 'HERBATECH')->value('id'));
    }

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

    private function revenue(string $period, float $target, ?float $actual): void
    {
        RevenueTarget::create(['period' => $period, 'target' => $target, 'actual' => $actual]);
    }

    public function test_apex_score_follows_the_workbook_formula(): void
    {
        // Cascading_Revenue_Rasio_KPI_Erdigma_2026.xlsx, sheet Asumsi:
        // "skor puncak = 0,45 × F1 + 0,55 × F2".
        $this->period();
        $this->ratio('2026-08', 80);          // F2 = 80
        $this->revenue('2026-08', 100, 90);   // F1 = 90

        // 0,45 × 90 + 0,55 × 80 = 40,5 + 44 = 84,5
        Livewire::test(BscDashboard::class)->assertViewHas('apexScore', 84.5);
    }

    public function test_objectives_and_action_plans_do_not_enter_the_apex_formula(): void
    {
        // Menurut metodologinya, keduanya menggerakkan rasio lewat pos akun —
        // memasukkannya lagi ke skor puncak berarti menghitung dua kali.
        $this->period();
        $this->ratio('2026-08', 80);
        $this->revenue('2026-08', 100, 90);
        $objective = $this->objective('2026-08', 10);
        ActionPlan::create([
            'department_objective_id' => $objective->id,
            'title' => 'Perbaikan lini produksi',
            'owner_dept' => 'PRO',
            'progress_pct' => 5,
            'status' => 'On Progress',
        ]);

        Livewire::test(BscDashboard::class)->assertViewHas('apexScore', 84.5);
    }

    public function test_revenue_achievement_is_cumulative_from_january(): void
    {
        // Sheet L1 bagian G: pencapaian kumulatif = Σ realisasi ÷ Σ target Jan s.d. bulan berjalan.
        $this->period('2026-03');
        $this->revenue('2026-01', 100, 120);
        $this->revenue('2026-02', 100, 60);
        $this->revenue('2026-03', 100, 90);

        // (120 + 60 + 90) ÷ 300 = 90%
        $this->assertSame(90.0, RevenueTarget::cumulativeAchievement('2026-03'));
        // Tidak menarik bulan dari tahun lain maupun bulan sesudahnya.
        $this->assertSame(90.0, RevenueTarget::cumulativeAchievement('2026-02'));
    }

    public function test_revenue_achievement_is_capped_at_one_hundred_percent(): void
    {
        $this->revenue('2026-08', 100, 150);

        $this->assertSame(100.0, RevenueTarget::cumulativeAchievement('2026-08'));
    }

    public function test_revenue_without_any_target_is_not_scored_as_zero(): void
    {
        $this->assertNull(RevenueTarget::cumulativeAchievement('2026-08'));
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
        $this->revenue('2026-08', 100, 95);

        $html = Livewire::test(BscDashboard::class)->html();

        $this->assertStringContainsString('Rata-rata terbobot', $html);
        $this->assertStringContainsString('45% Revenue', $html);
        $this->assertStringContainsString('55% Rasio Keuangan', $html);
        // Label lama tidak lagi muncul: angka revenue "IDR 115.2M" tidak berasal dari data.
        $this->assertStringNotContainsString('115.2M', $html);
    }

    public function test_without_a_revenue_target_the_ratio_score_carries_the_full_weight(): void
    {
        $this->period();
        $this->ratio('2026-08', 90);

        $html = Livewire::test(BscDashboard::class)->html();

        // Belum ada target revenue: F1 dikeluarkan, bobotnya dibagi ke F2.
        $this->assertStringContainsString('100% Rasio Keuangan', $html);
        $this->assertStringContainsString('belum ada target', $html);
        Livewire::test(BscDashboard::class)->assertViewHas('apexScore', 90.0);
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
