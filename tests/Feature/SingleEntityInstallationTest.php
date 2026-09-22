<?php

namespace Tests\Feature;

use App\Livewire\ManageUsers;
use App\Models\Entity;
use App\Models\User;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BSC_HOLDING_MODE=false — instalasi satu entitas: semua pengguna terkunci di
 * entitas instalasi, tanpa pengalih entitas dan tanpa Konsolidasi Holding.
 */
class SingleEntityInstallationTest extends TestCase
{
    use RefreshDatabase;

    private Entity $erdigma;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bsc.holding_mode' => false, 'bsc.default_entity' => 'ERDIGMA']);
        app(EntityContext::class)->forget();
        $this->erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function superAdmin(?int $entityId = null): User
    {
        $user = User::create([
            'name' => 'Super Admin', 'email' => 'admin'.($entityId ?? '').'@contoh.test',
            'password' => bcrypt('Herbatech#2026aman'), 'is_active' => true, 'entity_id' => $entityId,
        ]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_everyone_is_locked_to_the_installation_entity(): void
    {
        $this->actingAs($this->superAdmin());
        $this->assertSame($this->erdigma->id, app(EntityContext::class)->id());

        // Bahkan pengguna yang tertaut ke entitas lain tetap melihat entitas instalasi.
        app(EntityContext::class)->forget();
        $this->actingAs($this->superAdmin(Entity::where('code', 'AEJ')->value('id')));
        $this->assertSame($this->erdigma->id, app(EntityContext::class)->id());
    }

    public function test_there_is_no_entity_switcher(): void
    {
        $admin = $this->superAdmin();

        $this->assertFalse(app(EntityContext::class)->canSwitch($admin));

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Tampilkan data entitas')
            ->assertDontSee('Konsolidasi Holding')
            ->assertSee('Erdigma');
    }

    public function test_switching_entity_is_refused(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('entity.switch'), ['entity_id' => Entity::where('code', 'AEJ')->value('id')])
            ->assertForbidden();

        $this->assertSame($this->erdigma->id, app(EntityContext::class)->id());
    }

    public function test_the_consolidation_page_is_closed(): void
    {
        $this->actingAs($this->superAdmin())->get(route('consolidation'))->assertForbidden();
    }

    public function test_new_users_belong_to_the_installation_entity(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageUsers::class)
            ->assertViewHas('entities', fn ($e) => $e->pluck('code')->all() === ['ERDIGMA'])
            ->call('openCreate')
            ->assertSet('entity_id', (string) $this->erdigma->id)
            ->assertDontSeeHtml('Semua entitas (level holding)');

        // Entitas lain tidak dapat dipilih lewat permintaan buatan.
        Livewire::test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'Staf Baru')->set('email', 'staf@contoh.test')
            ->set('password', 'Herbatech#2026aman')->set('role', 'Viewer')
            ->set('entity_id', (string) Entity::where('code', 'AEJ')->value('id'))
            ->call('saveUser')
            ->assertHasErrors('entity_id');
    }

    public function test_holding_mode_brings_the_switcher_back(): void
    {
        config(['bsc.holding_mode' => true]);
        app(EntityContext::class)->forget();
        $admin = $this->superAdmin();

        $this->assertTrue(app(EntityContext::class)->canSwitch($admin));
        $this->actingAs($admin)->get(route('consolidation'))->assertOk();
    }
}
