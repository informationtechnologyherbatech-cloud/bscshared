<?php

namespace Tests\Feature;

use App\Livewire\BscDashboard;
use App\Livewire\RevenueTargets;
use App\Models\ActionPlan;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\Period;
use App\Models\RevenuePlan;
use App\Models\RevenueTarget;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tiap tingkat piramida harus sama dengan sumber datanya:
 *   T1 = F1 dari target & realisasi revenue bulanan (kumulatif, maks 100)
 *   T2 = F2 dari 19 rasio hasil hitungan pos akun (rubrik × bobot)
 *   T3 = rata-rata capaian sasaran mutu periode itu
 *   T4 = rata-rata progres program kerja yang tertaut ke sasaran periode itu
 *   Apex = 0,45 × F1 + 0,55 × F2 (tingkat tanpa data dikeluarkan).
 */
class PyramidTierSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class); // Erdigma 2026-08: 19 rasio (F2 94,1), 8 sasaran contoh
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-erdigma@contoh.test', 'password' => bcrypt('x'), 'is_active' => true,
            'entity_id' => Entity::where('code', 'ERDIGMA')->value('id'),
        ]);
        $user->assignRole('Super Admin');
        $this->actingAs($user);
    }

    public function test_an_approved_target_without_monthly_phasing_is_explained(): void
    {
        RevenuePlan::create(['year' => '2026', 'approved_target' => 1e9]);

        Livewire::test(BscDashboard::class)
            ->assertViewHas('revenueScore', null)
            ->assertSee('target bulanan belum difasing')
            ->assertViewHas('apexScore', 94.1); // F1 dikeluarkan, F2 memikul seluruh bobot
    }

    public function test_targets_without_any_actual_are_not_scored_as_zero(): void
    {
        foreach (['01', '02'] as $b) {
            RevenueTarget::create(['period' => '2026-'.$b, 'target' => 100, 'actual' => null]);
        }

        Livewire::test(BscDashboard::class)
            ->assertViewHas('revenueScore', null)
            ->assertSee('belum ada realisasi')
            ->assertViewHas('apexScore', 94.1);
    }

    public function test_tier_one_and_the_apex_follow_the_monthly_revenue(): void
    {
        RevenuePlan::create(['year' => '2026', 'approved_target' => 1200]);
        foreach (range(1, 8) as $b) {
            RevenueTarget::create(['period' => sprintf('2026-%02d', $b), 'target' => 100, 'actual' => 90]);
        }

        Livewire::test(BscDashboard::class)
            ->assertViewHas('revenueScore', 90.0) // 720 ÷ 800
            ->assertViewHas('apexScore', round(0.45 * 90 + 0.55 * 94.1, 2))
            ->call('selectLevel', 1)
            ->assertSee('Rp 800')      // target kumulatif Jan–08
            ->assertSee('Rp 720')      // realisasi kumulatif
            ->assertSee('Rp 1.200')    // target setahun
            ->assertDontSee('IDR 120.00 M')
            ->assertDontSee('96.00%');
    }

    public function test_tier_two_is_the_account_post_ratio_score(): void
    {
        Livewire::test(BscDashboard::class)
            ->assertViewHas('avgRatioScore', 94.1)
            ->assertViewHas('ratioCount', 19);
    }

    public function test_tier_three_and_four_follow_their_period_data(): void
    {
        $o = DepartmentObjective::where('period', '2026-08')->get();
        ActionPlan::create(['department_objective_id' => $o->first()->id, 'title' => 'Program A', 'owner_dept' => 'SCM', 'progress_pct' => 40, 'status' => 'On Progress']);
        ActionPlan::create(['department_objective_id' => $o->last()->id, 'title' => 'Program B', 'owner_dept' => 'BMK', 'progress_pct' => 80, 'status' => 'On Progress']);

        Livewire::test(BscDashboard::class)
            ->assertViewHas('avgObjScore', round((float) $o->avg('achievement_pct'), 2))
            ->assertViewHas('avgActionProgress', 60.0);
    }

    public function test_clicking_a_tier_scrolls_to_the_drill_down(): void
    {
        Livewire::test(BscDashboard::class)
            ->assertSeeHtml('id="telusurDetail"')
            ->call('selectLevel', 3)
            ->assertSet('activeLevel', 3)
            ->assertDispatched('telusur-detail');
    }

    public function test_saving_an_annual_target_without_monthly_targets_warns(): void
    {
        Period::firstOrCreate(['period' => '2026-08'], ['status' => 'OPEN', 'apex_score' => 0]);

        Livewire::test(RevenueTargets::class, ['year' => '2026'])
            ->set('approvedTarget', '1000000000')
            ->call('save')
            ->assertSee('target bulanan masih kosong');
    }
}
