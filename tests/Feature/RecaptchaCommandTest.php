<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\Recaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jalan keluar darurat: mematikan reCAPTCHA tanpa bisa masuk ke aplikasi.
 */
class RecaptchaCommandTest extends TestCase
{
    use RefreshDatabase;

    private function configureRecaptcha(): void
    {
        AppSetting::setValue(Recaptcha::ENABLED_KEY, '1');
        AppSetting::setValue(Recaptcha::SITE_KEY, 'site-key-uji');
        AppSetting::setSecret(Recaptcha::SECRET_KEY, 'secret-key-uji');
    }

    public function test_the_command_disables_recaptcha(): void
    {
        $this->configureRecaptcha();
        $this->assertTrue(app(Recaptcha::class)->enabled());

        $this->artisan('recaptcha disable')->assertSuccessful();

        AppSetting::flushCache();
        $this->assertFalse(app(Recaptcha::class)->enabled());
    }

    public function test_the_command_clears_the_settings_cache_so_direct_sql_edits_take_effect(): void
    {
        $this->configureRecaptcha();

        // Hangatkan cache, lalu ubah tabel langsung — persis skenario pemulihan
        // darurat lewat SQL.
        $this->assertSame('1', AppSetting::getValue(Recaptcha::ENABLED_KEY));
        DB::table('app_settings')
            ->where('key', Recaptcha::ENABLED_KEY)
            ->update(['value' => '0']);
        $this->assertSame('1', AppSetting::getValue(Recaptcha::ENABLED_KEY), 'Nilai lama masih tercache.');

        $this->artisan('recaptcha status')->assertSuccessful();

        $this->assertSame('0', AppSetting::getValue(Recaptcha::ENABLED_KEY));
    }

    public function test_the_command_refuses_to_enable_recaptcha_without_both_keys(): void
    {
        $this->artisan('recaptcha enable')->assertFailed();

        AppSetting::flushCache();
        $this->assertFalse(app(Recaptcha::class)->toggledOn());
    }

    public function test_the_command_can_enable_recaptcha_once_the_keys_exist(): void
    {
        $this->configureRecaptcha();
        AppSetting::setValue(Recaptcha::ENABLED_KEY, '0');

        $this->artisan('recaptcha enable')->assertSuccessful();

        AppSetting::flushCache();
        $this->assertTrue(app(Recaptcha::class)->enabled());
    }

    public function test_an_unknown_action_fails_loudly(): void
    {
        $this->artisan('recaptcha hapus-semuanya')->assertFailed();
    }
}
