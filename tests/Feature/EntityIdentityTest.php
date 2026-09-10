<?php

namespace Tests\Feature;

use App\Livewire\AppSettings;
use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EntityIdentityTest extends TestCase
{
    use RefreshDatabase;

    /** Halaman Setting Sistem kini menuntut izin, jadi tesnya harus login. */
    private function actingAsSuperAdmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs(
            User::where('email', 'superadmin@herbatech.co.id')->firstOrFail()
        );
    }

    public function test_app_version_helper_reads_application_config(): void
    {
        config()->set('app.version', '2.4.1');

        $this->assertSame('v2.4.1', app_version());
        $this->assertSame('2.4.1', app_version(false));
    }

    public function test_app_version_helper_normalises_a_prefixed_or_empty_value(): void
    {
        config()->set('app.version', 'v3.0.0');
        $this->assertSame('v3.0.0', app_version());

        config()->set('app.version', '');
        $this->assertSame('v1.0.0', app_version());
    }

    public function test_entity_helpers_fall_back_to_config_defaults(): void
    {
        $this->assertSame(config('entity.defaults.company_name'), company_name());
        $this->assertSame(config('entity.defaults.entity_name'), entity_name());
        $this->assertSame(config('entity.defaults.app_name'), app_display_name());
    }

    public function test_entity_helpers_prefer_stored_settings(): void
    {
        AppSetting::setMany([
            'entity_name' => 'Nusantara Farma',
            'company_name' => 'PT Nusantara Farma Sejahtera',
            'company_email' => 'halo@nusantarafarma.co.id',
            'app_year' => '2027',
        ]);

        $this->assertSame('Nusantara Farma', entity_name());
        $this->assertSame('PT Nusantara Farma Sejahtera', company_name());
        $this->assertSame('halo@nusantarafarma.co.id', entity('company_email'));
        $this->assertSame('PT Nusantara Farma Sejahtera © 2027', entity_copyright());
    }

    public function test_settings_page_persists_the_entity_identity(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(AppSettings::class)
            ->set('entity_name', 'Nusantara Farma')
            ->set('company_name', 'PT Nusantara Farma Sejahtera')
            ->set('company_address', 'Jl. Industri Raya No. 10, Bekasi')
            ->set('company_phone', '(021) 8899001')
            ->set('company_email', 'halo@nusantarafarma.co.id')
            ->set('company_website', 'https://nusantarafarma.co.id')
            ->call('saveIdentity')
            ->assertHasNoErrors();

        $this->assertSame('Nusantara Farma', AppSetting::getValue('entity_name'));
        $this->assertSame('PT Nusantara Farma Sejahtera', AppSetting::getValue('company_name'));
        $this->assertSame('Jl. Industri Raya No. 10, Bekasi', AppSetting::getValue('company_address'));
        $this->assertSame('(021) 8899001', AppSetting::getValue('company_phone'));
        $this->assertSame('halo@nusantarafarma.co.id', AppSetting::getValue('company_email'));
        $this->assertSame('https://nusantarafarma.co.id', AppSetting::getValue('company_website'));
    }

    public function test_settings_page_rejects_an_empty_company_name_and_an_invalid_contact(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(AppSettings::class)
            ->set('company_name', '')
            ->set('company_email', 'bukan-email')
            ->call('saveIdentity')
            ->assertHasErrors(['company_name', 'company_email']);
    }

    public function test_settings_page_restores_the_configured_defaults(): void
    {
        $this->actingAsSuperAdmin();

        AppSetting::setValue('company_name', 'PT Entitas Lain');

        Livewire::test(AppSettings::class)
            ->call('resetIdentity');

        $this->assertSame(config('entity.defaults.company_name'), AppSetting::getValue('company_name'));
    }

    public function test_branding_url_follows_the_request_host_not_app_url(): void
    {
        // APP_URL kerap tidak menyertakan port yang dipakai saat pengembangan.
        config()->set('app.url', 'http://localhost');
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', 'x');
        AppSetting::setValue('app_logo', 'branding/logo.png');

        $this->assertSame(asset('storage/branding/logo.png'), entity_logo());
        $this->assertStringEndsWith('/storage/branding/logo.png', entity_logo());
    }

    public function test_branding_url_is_null_when_the_file_is_missing(): void
    {
        Storage::fake('public');
        AppSetting::setValue('app_logo', 'branding/hilang.png');

        $this->assertNull(entity_logo());
        $this->assertSame(asset('favicon.ico'), entity_favicon());
    }

    public function test_login_page_shows_the_configured_entity_instead_of_a_hardcoded_one(): void
    {
        AppSetting::setMany([
            'app_name' => 'Scorecard Nusantara',
            'company_name' => 'PT Nusantara Farma Sejahtera',
        ]);

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Scorecard Nusantara', false);
        $response->assertSee('PT Nusantara Farma Sejahtera', false);
        $response->assertDontSee('PT Herbatech Innopharma', false);
    }
}
