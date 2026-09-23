<?php

namespace Tests\Feature;

use App\Livewire\AppSettings;
use App\Livewire\EntitySources;
use App\Models\ApiKey;
use App\Models\Entity;
use App\Models\EntityDataSource;
use App\Models\PairingCode;
use App\Models\User;
use App\Support\Bsc\Sources\EntitySourceFactory;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pendaftaran entitas ke holding: di holding tidak ada kunci yang perlu diketik.
 *
 * Holding menerbitkan kode sekali pakai berumur pendek; aplikasi entitas membuat
 * kuncinya sendiri lalu mengirimkan alamat & kunci itu ke holding, yang memeriksa
 * baliknya sebelum menyimpan.
 */
class EntityPairingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    private function entitas(string $kode): Entity
    {
        return Entity::where('code', $kode)->firstOrFail();
    }

    private function loginHolding(): User
    {
        Config::set('bsc.holding_mode', true);
        $user = User::create([
            'name' => 'Admin Holding', 'email' => 'holding-'.uniqid().'@contoh.test',
            'password' => bcrypt('x'), 'is_active' => true, 'entity_id' => null,
        ]);
        $user->assignRole('Super Admin');
        $this->actingAs($user);
        app(EntityContext::class)->forget();

        return $user;
    }

    /** Jawaban /api/v1/ping milik entitas yang sah. */
    private function pingSah(string $kode = 'AEJ'): array
    {
        return ['data' => ['entity' => $kode, 'entity_code' => $kode, 'app' => 'Super Apps BSC', 'version' => 'v1.0.0', 'time' => now()->toIso8601String()]];
    }

    /* ─────────────────────────── Holding menerbitkan kode ─────────────────────────── */

    public function test_the_holding_issues_a_short_lived_single_use_code(): void
    {
        $this->loginHolding();

        $kode = Livewire::test(EntitySources::class)
            ->call('issuePairingCode', $this->entitas('AEJ')->id)
            ->assertSee('Kode pendaftaran')
            ->get('kodePendaftaran');

        $this->assertMatchesRegularExpression('/^PAIR-[A-Z2-9]{4}-[A-Z2-9]{4}$/', (string) $kode);

        // Yang tersimpan hanya sidik jarinya, dan masa berlakunya pendek.
        $baris = PairingCode::first();
        $this->assertSame(hash('sha256', $kode), $baris->code_hash);
        $this->assertStringNotContainsString($kode, (string) DB::table('pairing_codes')->value('code_hash'));
        $this->assertTrue($baris->expires_at->between(now(), now()->addMinutes(PairingCode::MASA_BERLAKU + 1)));

        // Menerbitkan kode baru membatalkan kode lama entitas itu.
        Livewire::test(EntitySources::class)->call('issuePairingCode', $this->entitas('AEJ')->id);
        $this->assertSame(1, PairingCode::where('entity_id', $this->entitas('AEJ')->id)->count());
    }

    public function test_only_holding_managers_may_issue_a_code(): void
    {
        $terikat = User::create([
            'name' => 'Admin Entitas', 'email' => 'terikat@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => $this->entitas('ERDIGMA')->id,
        ]);
        $terikat->assignRole('Super Admin');
        $this->actingAs($terikat);
        app(EntityContext::class)->forget();

        $this->get('/sumber-entitas')->assertForbidden();
        $this->assertSame(0, PairingCode::count());
    }

    /* ─────────────────────────── Entitas mendaftarkan dirinya ─────────────────────────── */

    public function test_an_entity_registers_itself_and_the_holding_types_nothing(): void
    {
        // 1. Holding menerbitkan kode.
        $this->loginHolding();
        $kode = Livewire::test(EntitySources::class)
            ->call('issuePairingCode', $this->entitas('AEJ')->id)
            ->get('kodePendaftaran');

        // 2. Aplikasi entitas mengirim alamat & kunci buatannya sendiri.
        Http::fake(['bsc.aej.co.id/*' => Http::response($this->pingSah(), 200)]); // pemeriksaan balik holding
        $respons = $this->postJson(route('api.pairing'), [
            'code' => $kode,
            'entity_code' => 'AEJ',
            'entity_url' => 'https://bsc.aej.co.id',
            'api_key' => 'bsc_live_dari_entitas',
        ])->assertOk();

        $this->assertStringContainsString('berhasil terhubung', $respons->json('data.message'));

        // 3. Sumber data terisi sendiri di holding, kunci tersimpan terenkripsi.
        $sumber = EntityDataSource::where('entity_id', $this->entitas('AEJ')->id)->first();
        $this->assertSame(EntityDataSource::API, $sumber->driver);
        $this->assertSame('https://bsc.aej.co.id', $sumber->api_url);
        $this->assertSame('bsc_live_dari_entitas', $sumber->api_key);
        $this->assertNotSame('bsc_live_dari_entitas', DB::table('entity_sources')->value('api_key'));
        // Menunggu pemeriksaan balik, yang dijalankan pada permintaan berikutnya.
        $this->assertSame('menunggu', $sumber->last_status);

        // Selama belum terverifikasi, sumbernya TIDAK dibaca sama sekali: siapa pun
        // yang memegang kode tidak bisa membuat angka karangannya muncul di holding.
        $this->assertSame('menunggu verifikasi',
            app(EntitySourceFactory::class)->describe($this->entitas('AEJ')));

        // Pemeriksaan balik berjalan sesudah halaman tampil (wire:init), bukan saat
        // halaman digambar — jadi membuka halaman tidak pernah mengubah data.
        Livewire::test(EntitySources::class)
            ->assertSee('AEJ')
            ->call('verifikasiPendaftaranBaru');

        $this->assertSame('ok', $sumber->fresh()->last_status);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v1/ping') && $r->hasHeader('X-API-KEY', 'bsc_live_dari_entitas'));

        // 4. Kodenya habis sekali pakai.
        $this->assertNotNull(PairingCode::first()->used_at);
        Http::fake(['bsc.aej.co.id/*' => Http::response($this->pingSah(), 200)]);
        $this->postJson(route('api.pairing'), [
            'code' => $kode, 'entity_code' => 'AEJ',
            'entity_url' => 'https://bsc.aej.co.id', 'api_key' => 'bsc_live_lagi',
        ])->assertStatus(401);
    }

    public function test_the_entity_side_button_creates_and_sends_its_own_key(): void
    {
        Config::set('bsc.holding_mode', false);
        Config::set('bsc.default_entity', 'ERDIGMA');
        app(EntityContext::class)->forget();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@contoh.test', 'password' => bcrypt('x'), 'is_active' => true, 'entity_id' => null]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        Http::fake(['bsc.emc.co.id/*' => Http::response(['data' => ['entity' => 'ERDIGMA', 'message' => 'Entitas Erdigma berhasil terhubung ke holding.']], 200)]);

        Livewire::test(AppSettings::class)
            ->set('activeTab', 'api')
            ->set('holdingUrl', 'https://bsc.emc.co.id')
            ->set('holdingCode', 'PAIR-AAAA-BBBB')
            ->call('daftarKeHolding')
            ->assertHasNoErrors();

        // Kunci dibuat di sini dan dikirim; yang tersimpan hanya sidik jarinya.
        $kunci = ApiKey::first();
        $this->assertSame('ERDIGMA', $kunci->entity_code);
        Http::assertSent(function ($r) use ($kunci) {
            $isi = $r->data();

            return str_contains($r->url(), '/api/v1/pairing')
                && $isi['entity_code'] === 'ERDIGMA'
                && $isi['code'] === 'PAIR-AAAA-BBBB'
                && ApiKey::fingerprint($isi['api_key']) === $kunci->key_hash;
        });
    }

    public function test_a_refused_registration_does_not_leave_a_stray_key(): void
    {
        Config::set('bsc.holding_mode', false);
        Config::set('bsc.default_entity', 'ERDIGMA');
        app(EntityContext::class)->forget();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin2@contoh.test', 'password' => bcrypt('x'), 'is_active' => true, 'entity_id' => null]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        Http::fake(['bsc.emc.co.id/*' => Http::response(['message' => 'Kode pendaftaran tidak dikenal, sudah dipakai, atau kedaluwarsa.'], 401)]);

        Livewire::test(AppSettings::class)
            ->set('activeTab', 'api')
            ->set('holdingUrl', 'https://bsc.emc.co.id')
            ->set('holdingCode', 'PAIR-SALAH-XX')
            ->call('daftarKeHolding');

        $this->assertSame(0, ApiKey::count()); // kunci yang gagal dipakai dibuang
    }

    /* ─────────────────────────── Penjagaan endpoint pendaftaran ─────────────────────────── */

    public function test_a_wrong_or_expired_code_is_refused(): void
    {
        $this->loginHolding();
        [$baris, $kode] = PairingCode::issue($this->entitas('AEJ'));

        $this->postJson(route('api.pairing'), [
            'code' => 'PAIR-XXXX-XXXX', 'entity_code' => 'AEJ',
            'entity_url' => 'https://bsc.aej.co.id', 'api_key' => 'k',
        ])->assertStatus(401);

        $baris->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->postJson(route('api.pairing'), [
            'code' => $kode, 'entity_code' => 'AEJ',
            'entity_url' => 'https://bsc.aej.co.id', 'api_key' => 'k',
        ])->assertStatus(401);

        $this->assertSame(0, EntityDataSource::count());
    }

    public function test_a_code_cannot_register_a_different_entity(): void
    {
        $this->loginHolding();
        [, $kode] = PairingCode::issue($this->entitas('AEJ'));

        $this->postJson(route('api.pairing'), [
            'code' => $kode, 'entity_code' => 'HERBATECH',
            'entity_url' => 'https://bsc.herbatech.co.id', 'api_key' => 'k',
        ])->assertStatus(403);

        $this->assertSame(0, EntityDataSource::count());
        // Kodenya tetap hangus: satu kode = satu percobaan, supaya pemegangnya
        // tidak dapat menebak-nebak entitas mana yang dimaksud.
        $this->assertNotNull(PairingCode::first()->used_at);
    }

    public function test_an_unverified_registration_is_held_back_but_never_deleted(): void
    {
        $this->loginHolding();
        [, $kode] = PairingCode::issue($this->entitas('AEJ'));

        // Pendaftaran diterima lebih dulu (status "menunggu"), lalu diperiksa pada
        // permintaan tersendiri — tanpa kedua aplikasi saling menunggu.
        Http::fake(['bsc.palsu.test/*' => Http::response($this->pingSah('HERBATECH'), 200)]);
        $this->postJson(route('api.pairing'), [
            'code' => $kode, 'entity_code' => 'AEJ',
            'entity_url' => 'https://bsc.palsu.test', 'api_key' => 'k',
        ])->assertOk();
        $this->assertSame('menunggu', EntityDataSource::first()->last_status);

        Livewire::test(EntitySources::class)->call('verifikasiPendaftaranBaru');

        // Alamat yang melayani entitas lain tidak lolos. Barisnya TIDAK dihapus —
        // satu gangguan jaringan tidak boleh menghapus pengaturan yang sudah benar —
        // tetapi tetap tertahan: selama belum terbukti, sumbernya tidak dibaca.
        $sumber = EntityDataSource::first();
        $this->assertNotNull($sumber);
        $this->assertSame('menunggu', $sumber->last_status);
        $this->assertStringContainsString('belum terverifikasi', (string) $sumber->last_message);
        $this->assertSame('menunggu verifikasi',
            app(EntitySourceFactory::class)->describe($this->entitas('AEJ')));

        // Kode sekali pakai tetap hangus; kegagalan pemeriksaan tidak menghidupkannya
        // kembali, sehingga siapa pun yang sempat melihat kode itu tidak dapat
        // memakainya untuk mendaftarkan alamat lain.
        $this->assertNotNull(PairingCode::first()->used_at);
        $this->postJson(route('api.pairing'), [
            'code' => $kode, 'entity_code' => 'AEJ',
            'entity_url' => 'https://bsc.penyerang.test', 'api_key' => 'k',
        ])->assertStatus(401);

        // Dengan kode baru dan alamat yang benar, pendaftarannya menimpa yang lama.
        [, $kodeBaru] = PairingCode::issue($this->entitas('AEJ'));
        Http::fake(['bsc.aej.co.id/*' => Http::response($this->pingSah(), 200)]);
        $this->postJson(route('api.pairing'), [
            'code' => $kodeBaru, 'entity_code' => 'AEJ',
            'entity_url' => 'https://bsc.aej.co.id', 'api_key' => 'k',
        ])->assertOk();
        Livewire::test(EntitySources::class)->call('verifikasiPendaftaranBaru');

        $this->assertSame(1, EntityDataSource::count());
        $this->assertSame('ok', EntityDataSource::first()->last_status);
        $this->assertNotNull(PairingCode::first()->used_at);
    }

    public function test_an_entity_installation_does_not_accept_registrations(): void
    {
        Config::set('bsc.holding_mode', false);
        app(EntityContext::class)->forget();

        $this->postJson(route('api.pairing'), [
            'code' => 'PAIR-AAAA-BBBB', 'entity_code' => 'AEJ',
            'entity_url' => 'https://bsc.aej.co.id', 'api_key' => 'k',
        ])->assertStatus(409);
    }
}
