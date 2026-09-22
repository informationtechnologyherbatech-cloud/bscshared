<?php

namespace Tests\Feature;

use App\Livewire\BscDashboard;
use App\Livewire\DepartmentObjectives;
use App\Livewire\ManageUsers;
use App\Livewire\WorkUnits;
use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\User;
use App\Models\WorkUnit;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Satu aplikasi untuk empat entitas: data tiap entitas tidak boleh terlihat
 * dari entitas lain, dan hanya pengguna level holding yang dapat berpindah.
 */
class MultiEntityTest extends TestCase
{
    use RefreshDatabase;

    private function entity(string $code): Entity
    {
        return Entity::where('code', $code)->firstOrFail();
    }

    private function user(string $role, ?Entity $entity = null): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Pengguna '.$role,
            'email' => str($role)->slug().'-'.($entity?->code ?? 'holding').'@contoh.test',
            'password' => bcrypt('Herbatech#2026aman'),
            'is_active' => true,
            'entity_id' => $entity?->id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** Buat data sebuah entitas tanpa bergantung pada pengguna yang login. */
    private function dataFor(Entity $entity, string $kpi, float $capaian): void
    {
        $konteks = app(EntityContext::class);
        $konteks->use($entity->id);

        Period::firstOrCreate(['period' => '2026-08'], ['status' => 'OPEN', 'apex_score' => 0]);
        FinancialRatio::create([
            'period' => '2026-08', 'category' => 'Likuiditas', 'ratio_name' => 'Rasio '.$kpi,
            'target' => 1, 'actual' => 1, 'achievement_pct' => $capaian, 'status' => 'Tercapai',
        ]);
        DepartmentObjective::create([
            'period' => '2026-08', 'dept_code' => 'SCM', 'kpi_code' => $kpi, 'kpi_name' => 'Sasaran '.$kpi,
            'polarity' => 'Naik', 'target' => 100, 'actual' => $capaian, 'achievement_pct' => $capaian, 'status' => 'Tercapai',
        ]);

        $konteks->forget();
    }

    /* ------------------------------------------------------------- struktur */

    public function test_the_four_entities_exist_with_their_own_units(): void
    {
        $this->assertSame(
            ['HERBAEMAS', 'HERBATECH', 'AEJ', 'ERDIGMA'],
            Entity::active()->pluck('code')->all()
        );

        $erdigma = $this->entity('ERDIGMA');
        $this->assertSame(Entity::DIGITAL_MARKETING, $erdigma->industry);
        $this->assertSame(17, $erdigma->workUnits()->count());
        $this->assertTrue($erdigma->workUnits()->where('code', 'TTC')->exists());

        // Entitas manufaktur memakai struktur departemen pabrik.
        $this->assertTrue($this->entity('AEJ')->workUnits()->where('code', 'QC')->exists());
        $this->assertFalse($this->entity('AEJ')->workUnits()->where('code', 'TTC')->exists());
    }

    /* ------------------------------------------------------------- isolasi */

    public function test_a_user_only_sees_the_data_of_their_own_entity(): void
    {
        $erdigma = $this->entity('ERDIGMA');
        $herbatech = $this->entity('HERBATECH');

        $this->dataFor($erdigma, 'KPI-ERDIGMA', 90);
        $this->dataFor($herbatech, 'KPI-HERBATECH', 60);

        $this->actingAs($this->user('Viewer', $erdigma));

        Livewire::test(DepartmentObjectives::class)
            ->assertSee('KPI-ERDIGMA')
            ->assertDontSee('KPI-HERBATECH');
    }

    public function test_the_apex_score_is_computed_per_entity(): void
    {
        $erdigma = $this->entity('ERDIGMA');
        $herbatech = $this->entity('HERBATECH');

        $this->dataFor($erdigma, 'KPI-E', 90);
        $this->dataFor($herbatech, 'KPI-H', 60);

        $this->actingAs($this->user('Viewer', $erdigma));
        Livewire::test(BscDashboard::class)->assertViewHas('apexScore', 90.0);

        app(EntityContext::class)->forget();

        $this->actingAs($this->user('Viewer', $herbatech));
        Livewire::test(BscDashboard::class)->assertViewHas('apexScore', 60.0);
    }

    public function test_new_rows_are_stamped_with_the_active_entity(): void
    {
        $erdigma = $this->entity('ERDIGMA');
        $this->actingAs($this->user('Super Admin', $erdigma));

        $objective = DepartmentObjective::create([
            'period' => '2026-08', 'dept_code' => 'TTC', 'kpi_code' => 'TTC-H01', 'kpi_name' => 'Revenue TikTok',
            'polarity' => 'Naik', 'target' => 100, 'actual' => 0, 'achievement_pct' => 0, 'status' => 'Di Bawah Target',
        ]);

        $this->assertSame($erdigma->id, $objective->entity_id);
    }

    public function test_a_row_of_another_entity_cannot_be_reached_by_its_id(): void
    {
        // Mengirim ID milik entitas lain langsung ke aksi Livewire tidak boleh
        // membuka datanya — pembatas berlaku juga pada findOrFail.
        $unitAej = WorkUnit::withoutGlobalScopes()
            ->where('entity_id', $this->entity('AEJ')->id)
            ->where('code', 'QC')
            ->firstOrFail();

        $this->actingAs($this->user('Super Admin', $this->entity('ERDIGMA')));

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(WorkUnits::class)->call('openEdit', $unitAej->id);
    }

    public function test_the_same_period_can_exist_in_several_entities(): void
    {
        $this->dataFor($this->entity('ERDIGMA'), 'KPI-1', 90);
        $this->dataFor($this->entity('AEJ'), 'KPI-2', 90);

        $this->assertSame(2, Period::withoutGlobalScopes()->where('period', '2026-08')->count());
    }

    /* ------------------------------------------------------------ pengalih */

    public function test_a_holding_user_can_switch_entity(): void
    {
        $holding = $this->user('Super Admin');
        $aej = $this->entity('AEJ');

        $this->actingAs($holding)
            ->post(route('entity.switch'), ['entity_id' => $aej->id])
            ->assertRedirect();

        $this->assertSame($aej->id, app(EntityContext::class)->id());
    }

    public function test_a_user_bound_to_one_entity_cannot_switch(): void
    {
        $user = $this->user('Super Admin', $this->entity('ERDIGMA'));

        $this->actingAs($user)
            ->post(route('entity.switch'), ['entity_id' => $this->entity('AEJ')->id])
            ->assertForbidden();
    }

    public function test_the_switcher_is_shown_only_to_holding_users(): void
    {
        $this->actingAs($this->user('Super Admin'))
            ->get(route('dashboard'))
            ->assertSee('Tampilkan data entitas', false);

        app(EntityContext::class)->forget();

        $this->actingAs($this->user('Super Admin', $this->entity('ERDIGMA')))
            ->get(route('dashboard'))
            ->assertDontSee('Tampilkan data entitas', false)
            ->assertSee('Erdigma', false);
    }

    /* ------------------------------------------------- manajemen pengguna */

    public function test_a_super_admin_can_bind_a_user_to_an_entity_and_its_unit(): void
    {
        $this->actingAs($this->user('Super Admin'));
        $erdigma = $this->entity('ERDIGMA');

        Livewire::test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'Staf TikTok')
            ->set('email', 'staf-ttc@erdigma.test')
            ->set('password', 'Herbatech#2026aman')
            ->set('role', 'Operator')
            ->set('entity_id', $erdigma->id)
            ->set('dept_code', 'TTC')
            ->call('saveUser')
            ->assertHasNoErrors();

        $baru = User::where('email', 'staf-ttc@erdigma.test')->firstOrFail();
        $this->assertSame($erdigma->id, $baru->entity_id);
        $this->assertSame('TTC', $baru->dept_code);
    }

    public function test_the_unit_must_belong_to_the_chosen_entity(): void
    {
        $this->actingAs($this->user('Super Admin'));

        // QC adalah departemen manufaktur, bukan unit Erdigma.
        Livewire::test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'Salah Unit')
            ->set('email', 'salah-unit@erdigma.test')
            ->set('password', 'Herbatech#2026aman')
            ->set('role', 'Operator')
            ->set('entity_id', $this->entity('ERDIGMA')->id)
            ->set('dept_code', 'QC')
            ->call('saveUser')
            ->assertHasErrors('dept_code');
    }

    public function test_changing_the_entity_clears_the_previous_unit(): void
    {
        $this->actingAs($this->user('Super Admin'));

        Livewire::test(ManageUsers::class)
            ->call('openCreate')
            ->set('entity_id', $this->entity('ERDIGMA')->id)
            ->set('dept_code', 'TTC')
            ->set('entity_id', $this->entity('AEJ')->id)
            ->assertSet('dept_code', '');
    }

    /* ------------------------------------------------ objective departemen */

    public function test_the_department_filter_lists_every_active_unit_of_the_entity(): void
    {
        $erdigma = $this->entity('ERDIGMA');
        $this->actingAs($this->user('Viewer', $erdigma));

        // Unit yang belum punya satu pun sasaran mutu tetap muncul — sebelumnya
        // daftar ini hanya diturunkan dari data yang sudah ada.
        Livewire::test(DepartmentObjectives::class)
            ->assertSee('TTC — TikTok Commerce', false)
            ->assertSee('DIT — Data &amp; IT', false)
            ->assertDontSee('QC — Quality Control', false);
    }

    public function test_an_inactive_unit_is_left_out_of_the_filter(): void
    {
        $erdigma = $this->entity('ERDIGMA');
        WorkUnit::withoutGlobalScopes()->where('entity_id', $erdigma->id)->where('code', 'ERS')->update(['is_active' => false]);

        $this->actingAs($this->user('Viewer', $erdigma));

        Livewire::test(DepartmentObjectives::class)->assertDontSee('ERS — Erspace', false);
    }
}
