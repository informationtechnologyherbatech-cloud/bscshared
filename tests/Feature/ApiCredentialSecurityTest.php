<?php

namespace Tests\Feature;

use App\Livewire\AppSettings;
use App\Livewire\EntitySources;
use App\Models\ApiAccessLog;
use App\Models\ApiKey;
use App\Models\Entity;
use App\Models\EntityDataSource;
use App\Models\User;
use App\Support\Bsc\EntitySummaryBuilder;
use App\Support\Bsc\Sources\DatabaseEntitySource;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kredensial entitas hanya dipegang holding.
 *
 * Entitas menyimpan SIDIK JARI kunci (cukup untuk memeriksa, tidak cukup untuk
 * memakai), pemakaiannya dibatasi daftar IP & masa berlaku, dan setiap
 * permintaan — diterima maupun ditolak — tercatat. Untuk mode database, holding
 * memakai pengguna baca-saja khusus tiap entitas, bukan kredensialnya sendiri.
 */
class ApiCredentialSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Config::set('bsc.holding_mode', false);
        Config::set('bsc.default_entity', 'ERDIGMA');
        app(EntityContext::class)->forget();
    }

    private function entitas(string $kode): Entity
    {
        return Entity::where('code', $kode)->firstOrFail();
    }

    private function loginAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@contoh.test',
            'password' => bcrypt('x'), 'is_active' => true, 'entity_id' => null,
        ]);
        $user->assignRole('Super Admin');
        $this->actingAs($user);

        return $user;
    }

    /* ─────────────────── Kunci tidak tersimpan dalam bentuk yang bisa dipakai ─────────────────── */

    public function test_the_entity_stores_only_a_fingerprint_of_the_key(): void
    {
        [$kunci, $utuh] = ApiKey::issue('Holding EMC', 'ERDIGMA');

        $baris = (array) DB::table('api_keys')->where('id', $kunci->id)->first();

        $this->assertArrayNotHasKey('key', $baris); // kolom kunci polos sudah tidak ada
        $this->assertNotContains($utuh, $baris);
        $this->assertSame(hash('sha256', $utuh), $baris['key_hash']);
        $this->assertSame(substr($utuh, 0, 16), $baris['prefix']);

        // Sidik jari tetap dapat memeriksa kunci aslinya.
        $this->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping')->assertOk();
    }

    public function test_a_new_key_is_shown_once_and_never_again(): void
    {
        $this->loginAdmin();

        $komponen = Livewire::test(AppSettings::class)
            ->set('activeTab', 'api')
            ->set('newKeyName', 'Holding EMC')
            ->call('generateApiKey');

        $utuh = $komponen->get('kunciBaru');
        $this->assertStringStartsWith('bsc_live_', (string) $utuh);
        $komponen->assertSee($utuh);

        // Setelah ditutup, tidak ada lagi jalan menampilkannya.
        $komponen->call('hideNewKey')->assertDontSee($utuh);
        Livewire::test(AppSettings::class)->set('activeTab', 'api')->assertDontSee($utuh);
        $this->assertNull(ApiKey::first()->getAttribute('key'));
    }

    public function test_rotation_keeps_the_old_key_working_until_it_is_switched_off(): void
    {
        $this->loginAdmin();
        [$lama, $kunciLama] = ApiKey::issue('Lama', 'ERDIGMA');

        Livewire::test(AppSettings::class)->set('activeTab', 'api')
            ->set('newKeyName', 'Baru')->call('generateApiKey');
        $kunciBaru = Livewire::test(AppSettings::class)->set('activeTab', 'api')
            ->set('newKeyName', 'Baru 2')->call('generateApiKey')->get('kunciBaru');

        // Dua kunci sama-sama berlaku selama rotasi.
        $this->withHeader('X-API-KEY', $kunciLama)->getJson('/api/v1/ping')->assertOk();
        $this->withHeader('X-API-KEY', $kunciBaru)->getJson('/api/v1/ping')->assertOk();

        Livewire::test(AppSettings::class)->call('toggleKey', $lama->id);
        $this->withHeader('X-API-KEY', $kunciLama)->getJson('/api/v1/ping')->assertStatus(401);
        $this->withHeader('X-API-KEY', $kunciBaru)->getJson('/api/v1/ping')->assertOk();
    }

    /* ─────────────────────────── Pembatas pemakaian kunci ─────────────────────────── */

    public function test_an_expired_key_is_refused(): void
    {
        [$kunci, $utuh] = ApiKey::issue('Kedaluwarsa', 'ERDIGMA');
        $kunci->forceFill(['expires_at' => now()->subDay()])->save();

        $this->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping')->assertStatus(401);
        $this->assertSame(ApiAccessLog::KADALUWARSA, ApiAccessLog::latest('id')->first()->result);
    }

    public function test_a_key_only_works_from_the_allowed_addresses(): void
    {
        [, $utuh] = ApiKey::issue('Holding', 'ERDIGMA', '203.0.113.7, 10.8.0.0/16');

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping')->assertStatus(403);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping')->assertOk();

        // Alamat di dalam rentang CIDR ikut diterima.
        $this->withServerVariables(['REMOTE_ADDR' => '10.8.4.21'])
            ->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping')->assertOk();
    }

    public function test_a_key_without_an_address_list_works_from_anywhere(): void
    {
        [, $utuh] = ApiKey::issue('Tanpa batas', 'ERDIGMA');

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping')->assertOk();
    }

    /* ─────────────────────────────── Jejak akses ─────────────────────────────── */

    public function test_every_call_is_logged_without_ever_storing_the_key(): void
    {
        [$kunci, $utuh] = ApiKey::issue('Holding', 'ERDIGMA');

        $this->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/consolidation?period=2026-08')->assertOk();
        $this->withHeader('X-API-KEY', 'bsc_live_tebakan_orang_lain')->getJson('/api/v1/ping')->assertStatus(401);
        $this->flushHeaders()->getJson('/api/v1/ping')->assertStatus(401); // tanpa kunci sama sekali

        $jejak = ApiAccessLog::orderBy('id')->get();
        $this->assertCount(3, $jejak);
        $this->assertSame([ApiAccessLog::DITERIMA, ApiAccessLog::KUNCI_SALAH, ApiAccessLog::TANPA_KUNCI], $jejak->pluck('result')->all());
        $this->assertSame($kunci->id, $jejak[0]->api_key_id);
        $this->assertSame('api/v1/consolidation', $jejak[0]->path);

        // Kunci tidak pernah ikut tercatat — hanya awalannya.
        foreach ($jejak as $baris) {
            $this->assertNotSame($utuh, $baris->prefix);
            $this->assertStringNotContainsString($utuh, json_encode($baris->toArray()));
        }

        // Kunci yang TIDAK dikenal ditandai potongan sidik jarinya, bukan potongan
        // kuncinya: percobaan yang sama tetap dapat dikenali, tetapi jejak akses
        // tidak pernah memuat sebagian kunci sungguhan yang salah ketik.
        $dicoba = 'bsc_live_tebakan_orang_lain';
        $this->assertSame('?'.substr(ApiKey::fingerprint($dicoba), 0, 12), $jejak[1]->prefix);
        $this->assertStringNotContainsString(substr($dicoba, 0, 12), (string) $jejak[1]->prefix);
    }

    public function test_the_settings_page_shows_the_access_trail(): void
    {
        $this->loginAdmin();
        [, $utuh] = ApiKey::issue('Holding', 'ERDIGMA');
        $this->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping');
        $this->withHeader('X-API-KEY', 'salah-sekali')->getJson('/api/v1/ping');

        Livewire::test(AppSettings::class)->set('activeTab', 'api')
            ->assertSee('Akses API terakhir')
            ->assertSee('diterima')
            ->assertSee('kunci salah')
            ->assertDontSee($utuh);
    }

    /* ──────────────────── Kredensial database baca-saja per entitas ──────────────────── */

    public function test_a_database_source_uses_its_own_read_only_credentials(): void
    {
        $sumber = new DatabaseEntitySource(
            app(EntityContext::class),
            app(EntitySummaryBuilder::class),
            'db_bsc_aej',
            ['host' => '10.8.0.21', 'port' => '3307', 'username' => 'bsc_holding_ro', 'password' => 'rahasia'],
        );

        $config = $sumber->connectionConfig(['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306', 'username' => 'root', 'password' => 'induk', 'database' => 'db_holding']);

        $this->assertSame('db_bsc_aej', $config['database']);
        $this->assertSame('10.8.0.21', $config['host']);
        $this->assertSame('3307', $config['port']);
        $this->assertSame('bsc_holding_ro', $config['username']);
        $this->assertSame('rahasia', $config['password']);
        $this->assertFalse($config['sticky']);
    }

    public function test_without_its_own_credentials_the_application_credentials_are_used(): void
    {
        $sumber = new DatabaseEntitySource(app(EntityContext::class), app(EntitySummaryBuilder::class), 'db_bsc_aej');

        $config = $sumber->connectionConfig(['driver' => 'mysql', 'host' => '127.0.0.1', 'username' => 'root', 'password' => 'induk']);

        $this->assertSame('root', $config['username']);
        $this->assertSame('db_bsc_aej', $config['database']);
    }

    public function test_the_database_password_is_stored_encrypted(): void
    {
        Config::set('bsc.holding_mode', true);
        $this->loginAdmin();
        app(EntityContext::class)->forget();

        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->set('driver', 'database')
            ->set('databaseName', 'db_bsc_aej')
            ->set('dbUsername', 'bsc_holding_ro')
            ->set('dbPassword', 'sandi-rahasia')
            ->call('save')
            ->assertHasNoErrors();

        $mentah = (string) DB::table('entity_sources')->value('db_password');
        $this->assertNotSame('sandi-rahasia', $mentah);
        $this->assertStringNotContainsString('rahasia', $mentah);
        $this->assertSame('sandi-rahasia', EntityDataSource::first()->db_password);

        // Menyunting tanpa mengisi ulang kata sandi tidak menghapusnya.
        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->assertSet('dbPassword', '')
            ->assertSet('punyaSandiDb', true)
            ->set('databaseName', 'db_bsc_aej_baru')
            ->call('save');

        $this->assertSame('sandi-rahasia', EntityDataSource::first()->db_password);
    }
}
