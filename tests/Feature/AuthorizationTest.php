<?php

namespace Tests\Feature;

use App\Livewire\AppSettings;
use App\Livewire\BscDashboard;
use App\Livewire\StagingLogs;
use App\Livewire\SystemIntegration;
use App\Models\AppSetting;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\StagingLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Middleware rute hanya menjaga AKSES HALAMAN. Aksi Livewire dapat dipanggil
 * siapa pun yang berhasil membuka halamannya, sehingga setiap aksi yang menulis
 * data harus memeriksa izinnya sendiri.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Pengguna '.$role,
            'email' => str($role)->slug().'@contoh.test',
            'password' => bcrypt('rahasia123'),
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }

    /* ------------------------------------------------- periode (dashboard) */

    public function test_a_viewer_cannot_close_a_period_from_the_dashboard(): void
    {
        $this->actingAs($this->userWithRole('Viewer'));
        $period = Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);

        Livewire::test(BscDashboard::class)->call('togglePeriodStatus');

        $this->assertSame('OPEN', $period->fresh()->status);
    }

    public function test_a_viewer_cannot_create_a_period(): void
    {
        $this->actingAs($this->userWithRole('Viewer'));

        Livewire::test(BscDashboard::class)
            ->set('newPeriodInput', '2027-01')
            ->call('createNewPeriod');

        $this->assertDatabaseMissing('periods', ['period' => '2027-01']);
    }

    public function test_an_admin_fat_can_still_manage_periods(): void
    {
        $this->actingAs($this->userWithRole('Admin FAT'));
        $period = Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);

        Livewire::test(BscDashboard::class)->call('togglePeriodStatus');

        $this->assertSame('CLOSED', $period->fresh()->status);
    }

    /* --------------------------------------------------------- integrasi */

    public function test_a_viewer_cannot_simulate_an_inbound_payload(): void
    {
        $this->actingAs($this->userWithRole('Viewer'));

        Livewire::test(StagingLogs::class)->call('simulateInbound');

        $this->assertSame(0, StagingLog::count());
    }

    public function test_a_viewer_cannot_overwrite_financial_ratios_through_the_gateway(): void
    {
        $this->actingAs($this->userWithRole('Viewer'));

        Livewire::test(SystemIntegration::class)->call('processFinancePayload');

        $this->assertSame(0, FinancialRatio::count());
    }

    public function test_an_admin_fat_can_still_use_the_gateway(): void
    {
        $this->actingAs($this->userWithRole('Admin FAT'));

        Livewire::test(SystemIntegration::class)->call('processFinancePayload');

        $this->assertGreaterThan(0, FinancialRatio::count());
    }

    /* --------------------------------------------------------- pengaturan */

    public function test_an_admin_fat_cannot_rebrand_the_application(): void
    {
        // Admin FAT boleh membuka Setting Sistem karena memegang "manage apikey",
        // tetapi tidak memegang "manage settings".
        $this->actingAs($this->userWithRole('Admin FAT'));

        Livewire::test(AppSettings::class)
            ->set('entity_name', 'Entitas Bajakan')
            ->set('company_name', 'PT Bajakan')
            ->call('saveIdentity');

        $this->assertNotSame('PT Bajakan', AppSetting::getValue('company_name'));
    }

    public function test_an_admin_fat_cannot_switch_recaptcha_on_or_off(): void
    {
        $this->actingAs($this->userWithRole('Admin FAT'));

        Livewire::test(AppSettings::class)
            ->set('recaptcha_enabled', true)
            ->set('recaptcha_site_key', 'site')
            ->set('recaptcha_secret_key', 'secret')
            ->call('saveSecurity');

        $this->assertSame('0', (string) AppSetting::getValue('recaptcha_enabled', '0'));
    }

    public function test_an_admin_fat_can_still_manage_api_keys(): void
    {
        $this->actingAs($this->userWithRole('Admin FAT'));

        Livewire::test(AppSettings::class)
            ->set('newKeyName', 'Kunci Gateway Baru')
            ->call('generateApiKey');

        $this->assertDatabaseHas('api_keys', ['name' => 'Kunci Gateway Baru']);
    }

    public function test_a_super_admin_can_do_everything_on_the_settings_page(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));

        Livewire::test(AppSettings::class)
            ->set('entity_name', 'Entitas Sah')
            ->set('company_name', 'PT Entitas Sah')
            ->call('saveIdentity')
            ->assertHasNoErrors();

        $this->assertSame('PT Entitas Sah', AppSetting::getValue('company_name'));
    }
}
