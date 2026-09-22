<?php

namespace Tests\Feature;

use App\Livewire\AppSettings;
use App\Livewire\Auth\Login;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\Recaptcha;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Halaman Setting Sistem kini menuntut izin, jadi tesnya harus login. */
    private function actingAsSuperAdmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs(
            User::where('email', 'superadmin@emc.co.id')->firstOrFail()
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login:pengguna@contoh.test|127.0.0.1');
    }

    private function activeUser(string $password = 'Herbatech#2026aman'): User
    {
        return User::create([
            'name' => 'Pengguna Uji',
            'email' => 'pengguna@contoh.test',
            'password' => bcrypt($password),
            'is_active' => true,
        ]);
    }

    private function enableRecaptcha(): void
    {
        AppSetting::setValue(Recaptcha::ENABLED_KEY, '1');
        AppSetting::setValue(Recaptcha::SITE_KEY, 'site-key-uji');
        AppSetting::setSecret(Recaptcha::SECRET_KEY, 'secret-key-uji');
    }

    /* ---------------------------------------------------------------- header */

    public function test_security_headers_are_sent_on_every_page(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_content_security_policy_locks_down_script_and_frame_sources(): void
    {
        $csp = $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString('https://www.google.com', $csp);
    }

    public function test_content_security_policy_can_be_switched_off(): void
    {
        config()->set('security.csp.enabled', false);

        $this->get(route('login'))->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');
    }

    /* ------------------------------------------------------------ reCAPTCHA */

    public function test_recaptcha_is_off_until_it_is_switched_on_and_both_keys_exist(): void
    {
        $recaptcha = app(Recaptcha::class);
        $this->assertFalse($recaptcha->enabled());

        AppSetting::setValue(Recaptcha::ENABLED_KEY, '1');
        AppSetting::setValue(Recaptcha::SITE_KEY, 'site-key-uji');
        $this->assertFalse($recaptcha->enabled(), 'Tanpa secret key, reCAPTCHA tidak boleh aktif.');

        AppSetting::setSecret(Recaptcha::SECRET_KEY, 'secret-key-uji');
        $this->assertTrue($recaptcha->enabled());
    }

    public function test_login_works_normally_while_recaptcha_is_disabled(): void
    {
        $this->activeUser();
        Http::fake();

        Livewire::test(Login::class)
            ->set('email', 'pengguna@contoh.test')
            ->set('password', 'Herbatech#2026aman')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
        Http::assertNothingSent();
    }

    public function test_login_is_rejected_when_the_recaptcha_token_is_missing(): void
    {
        $this->activeUser();
        $this->enableRecaptcha();
        Http::fake();

        Livewire::test(Login::class)
            ->set('email', 'pengguna@contoh.test')
            ->set('password', 'Herbatech#2026aman')
            ->call('login')
            ->assertHasErrors('recaptchaToken');

        $this->assertGuest();
        // Token kosong ditolak tanpa perlu menghubungi Google.
        Http::assertNothingSent();
    }

    public function test_login_is_rejected_when_google_says_the_token_is_invalid(): void
    {
        $this->activeUser();
        $this->enableRecaptcha();
        Http::fake([Recaptcha::VERIFY_URL => Http::response(['success' => false])]);

        Livewire::test(Login::class)
            ->set('email', 'pengguna@contoh.test')
            ->set('password', 'Herbatech#2026aman')
            ->set('recaptchaToken', 'token-palsu')
            ->call('login')
            ->assertHasErrors('recaptchaToken');

        $this->assertGuest();
    }

    public function test_login_succeeds_when_google_accepts_the_token(): void
    {
        $this->activeUser();
        $this->enableRecaptcha();
        Http::fake([Recaptcha::VERIFY_URL => Http::response(['success' => true])]);

        Livewire::test(Login::class)
            ->set('email', 'pengguna@contoh.test')
            ->set('password', 'Herbatech#2026aman')
            ->set('recaptchaToken', 'token-sah')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_verification_fails_closed_when_google_cannot_be_reached(): void
    {
        $this->enableRecaptcha();
        Http::fake(fn () => throw new \RuntimeException('jaringan putus'));

        $this->assertFalse(app(Recaptcha::class)->verify('token-sah'));
    }

    public function test_the_login_page_only_loads_the_recaptcha_script_when_enabled(): void
    {
        $this->get(route('login'))->assertDontSee('recaptcha/api.js', false);

        $this->enableRecaptcha();

        $this->get(route('login'))
            ->assertSee('recaptcha/api.js', false)
            ->assertSee('site-key-uji', false)
            // Widget dan jembatan ke properti Livewire ikut ter-render.
            ->assertSee('class="g-recaptcha"', false)
            ->assertSee('data-callback="bscRecaptchaSolved"', false)
            ->assertSee('bscRecaptchaSolved = (token)', false)
            ->assertSee('recaptcha-reset', false);
    }

    public function test_the_secret_key_is_encrypted_at_rest_and_never_rendered(): void
    {
        $this->enableRecaptcha();

        $tersimpan = AppSetting::where('key', Recaptcha::SECRET_KEY)->value('value');

        $this->assertNotSame('secret-key-uji', $tersimpan);
        $this->assertSame('secret-key-uji', AppSetting::getSecret(Recaptcha::SECRET_KEY));

        $this->get(route('login'))->assertDontSee('secret-key-uji', false);
    }

    public function test_settings_page_keeps_the_stored_secret_when_the_field_is_left_blank(): void
    {
        $this->actingAsSuperAdmin();

        $this->enableRecaptcha();

        Livewire::test(AppSettings::class)
            ->set('recaptcha_enabled', true)
            ->set('recaptcha_site_key', 'site-key-baru')
            ->set('recaptcha_secret_key', '')
            ->call('saveSecurity')
            ->assertHasNoErrors();

        $this->assertSame('site-key-baru', app(Recaptcha::class)->siteKey());
        $this->assertSame('secret-key-uji', app(Recaptcha::class)->secretKey());
    }

    public function test_settings_page_requires_both_keys_before_recaptcha_can_be_switched_on(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(AppSettings::class)
            ->set('recaptcha_enabled', true)
            ->set('recaptcha_site_key', '')
            ->set('recaptcha_secret_key', '')
            ->call('saveSecurity')
            ->assertHasErrors(['recaptcha_site_key', 'recaptcha_secret_key']);
    }

    /* ------------------------------------------------------------------ XSS */

    public function test_an_svg_logo_is_rejected_because_it_can_carry_a_script(): void
    {
        $this->actingAsSuperAdmin();

        Storage::fake('public');

        Livewire::test(AppSettings::class)
            ->set('logoUpload', UploadedFile::fake()->create('jahat.svg', 8, 'image/svg+xml'))
            ->call('saveApp')
            ->assertHasErrors('logoUpload');

        $this->assertEmpty(AppSetting::getValue('app_logo'));
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_login_does_not_redirect_to_another_site_after_authentication(): void
    {
        $this->activeUser();

        Livewire::test(Login::class)
            ->set('email', 'pengguna@contoh.test')
            ->set('password', 'Herbatech#2026aman')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_a_javascript_url_is_rejected_for_the_company_website(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(AppSettings::class)
            ->set('company_website', 'javascript://kosong%0aalert(document.cookie)')
            ->call('saveEntity')
            ->assertHasErrors('company_website');
    }

    public function test_entity_values_are_escaped_in_the_rendered_page(): void
    {
        AppSetting::setMany([
            'company_name' => '<script>alert(1)</script>',
            'app_name' => 'BSC',
        ]);

        $response = $this->get(route('login'));

        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    /* -------------------------------------------------------- brute force */

    public function test_repeated_failures_are_throttled(): void
    {
        $this->activeUser();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->set('email', 'pengguna@contoh.test')
                ->set('password', 'salah')
                ->call('login')
                ->assertHasErrors('email');
        }

        Livewire::test(Login::class)
            ->set('email', 'pengguna@contoh.test')
            ->set('password', 'Herbatech#2026aman')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }
}
