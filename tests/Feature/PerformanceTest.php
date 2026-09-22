<?php

namespace Tests\Feature;

use App\Livewire\ActionPlans;
use App\Livewire\StagingLogs;
use App\Models\Entity;
use App\Models\User;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Data yang terus bertambah (log staging, program kerja) ditampilkan per halaman,
 * dan query terpenting punya indeks.
 */
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Entity $erdigma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        app(EntityContext::class)->use($this->erdigma->id);
        $admin = User::create(['name' => 'Admin', 'email' => 'perf@contoh.test', 'password' => bcrypt('x'), 'is_active' => true, 'entity_id' => $this->erdigma->id]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
    }

    public function test_hot_queries_are_indexed(): void
    {
        $this->assertTrue(Schema::hasIndex('department_objectives', 'dept_objectives_entity_period_index'));
        $this->assertTrue(Schema::hasIndex('financial_ratios', 'financial_ratios_entity_period_index'));
        $this->assertTrue(Schema::hasIndex('staging_logs', 'staging_logs_entity_created_index'));
        $this->assertTrue(Schema::hasIndex('action_plans', 'action_plans_entity_status_index'));
    }

    public function test_staging_logs_are_paginated_but_counted_in_full(): void
    {
        $sekarang = now();
        DB::table('staging_logs')->insert(array_map(fn ($i) => [
            'entity_id' => $this->erdigma->id, 'period' => '2026-08', 'dept_code' => 'SCM',
            'idempotency_key' => 'UJI-'.$i, 'status' => $i % 2 ? 'SCORED' : 'DELIVERED',
            'created_at' => $sekarang, 'updated_at' => $sekarang,
        ], range(1, 60)));
        $total = DB::table('staging_logs')->where('entity_id', $this->erdigma->id)->count();

        Livewire::test(StagingLogs::class)
            ->assertViewHas('logs', fn ($l) => $l->count() === 25 && $l->total() === $total)
            ->assertSee('dari '.$total)
            ->assertSeeHtml('page-item'); // tema paginasi Bootstrap, bukan Tailwind
    }

    public function test_action_plans_are_paginated(): void
    {
        $sasaran = DB::table('department_objectives')->where('entity_id', $this->erdigma->id)->value('id');
        $sekarang = now();
        DB::table('action_plans')->insert(array_map(fn ($i) => [
            'entity_id' => $this->erdigma->id, 'department_objective_id' => $sasaran, 'title' => 'Program '.$i,
            'owner_dept' => 'SCM', 'progress_pct' => 10, 'status' => 'On Progress', 'created_at' => $sekarang, 'updated_at' => $sekarang,
        ], range(1, 40)));

        Livewire::test(ActionPlans::class)
            ->assertViewHas('actionPlans', fn ($p) => $p->count() === 25 && $p->total() > 40);
    }
}
