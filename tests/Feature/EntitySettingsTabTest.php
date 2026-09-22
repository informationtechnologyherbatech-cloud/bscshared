<?php

namespace Tests\Feature;

use App\Livewire\AppSettings;
use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tab Entitas terpisah dari Identitas Aplikasi, dan identitas bawaan
 * mengikuti jenis instalasi di .env.
 */
class EntitySettingsTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@contoh.test', 'password' => bcrypt('x'), 'is_active' => true]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
    }

    private function runSyncMigration(): void
    {
        (require database_path('migrations/2026_09_22_170000_sync_entity_identity_with_installation.php'))->up();
    }

    public function test_the_entity_tab_is_separate_from_the_app_identity_tab(): void
    {
        Livewire::test(AppSettings::class)
            ->call('switchTab', 'entity')
            ->assertSet('activeTab', 'entity')
            ->assertSee('Jenis Instalasi')
            ->assertSee('Entitas Grup Terdaftar')
            ->assertSee('PT Erhanesia Digima Mukitama');
    }

    public function test_saving_the_app_tab_does_not_touch_the_entity_identity(): void
    {
        AppSetting::setMany(['company_name' => 'PT Tetap', 'entity_name' => 'Tetap']);

        Livewire::test(AppSettings::class)
            ->set('app_name', 'BSC Erdigma')
            ->set('company_name', '') // isian tab lain tidak ikut divalidasi/disimpan
            ->call('saveApp')
            ->assertHasNoErrors();

        $this->assertSame('BSC Erdigma', AppSetting::getValue('app_name'));
        $this->assertSame('PT Tetap', AppSetting::getValue('company_name'));
    }

    public function test_the_entity_identity_defaults_follow_the_installation(): void
    {
        // Instalasi satu entitas: identitas = profil entitas di .env.
        config(['entity.defaults.entity_name' => 'Erdigma', 'entity.defaults.company_name' => 'PT Erhanesia Digima Mukitama']);
        AppSetting::setMany(['entity_name' => 'Lain', 'company_name' => 'PT Lain']);

        Livewire::test(AppSettings::class)->call('resetEntity');

        $this->assertSame('Erdigma', AppSetting::getValue('entity_name'));
        $this->assertSame('PT Erhanesia Digima Mukitama', AppSetting::getValue('company_name'));
    }

    public function test_the_config_derives_identity_from_the_env(): void
    {
        $profil = config('entity.profiles');

        $this->assertSame('PT Erhanesia Digima Mukitama', $profil['ERDIGMA']['legal_name']);
        $this->assertSame('Erhanesia Mulia Corpora', config('entity.holding.name'));
        // phpunit.xml: BSC_HOLDING_MODE=true → identitas holding.
        $this->assertSame('Erhanesia Mulia Corpora', config('entity.defaults.company_name'));
    }

    public function test_migrate_replaces_the_old_herbatech_default_only(): void
    {
        config(['entity.defaults.entity_name' => 'Erdigma', 'entity.defaults.company_name' => 'PT Erhanesia Digima Mukitama']);

        // Masih bawaan lama → diganti.
        AppSetting::setMany(['entity_name' => 'Herbatech Innopharma', 'company_name' => 'PT Herbatech Innopharma Industry']);
        $this->runSyncMigration();
        $this->assertSame('Erdigma', AppSetting::getValue('entity_name'));
        $this->assertSame('PT Erhanesia Digima Mukitama', AppSetting::getValue('company_name'));

        // Sudah diubah pengguna → tidak disentuh.
        AppSetting::setMany(['entity_name' => 'Erdigma Pusat', 'company_name' => 'PT Nama Pilihan']);
        $this->runSyncMigration();
        $this->assertSame('PT Nama Pilihan', AppSetting::getValue('company_name'));
    }
}
