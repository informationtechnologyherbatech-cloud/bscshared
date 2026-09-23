<?php

namespace Tests\Feature;

use App\Livewire\EntitySources;
use App\Models\ApiAccessLog;
use App\Models\ApiKey;
use App\Models\Entity;
use App\Models\EntityDataSource;
use App\Models\IntercompanySale;
use App\Models\User;
use App\Support\Bsc\Consolidation;
use App\Support\Bsc\EntitySummary;
use App\Support\Bsc\Sources\EntitySourceFactory;
use App\Support\Bsc\Sources\EntitySourceSettings;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Perbaikan dari putaran uji & review "database per entitas".
 *
 * Yang dijaga di sini adalah hal-hal yang sekali meleset langsung berakibat
 * besar: holding menampilkan angka milik dirinya sendiri sebagai angka entitas,
 * satu entitas merobohkan halaman semua orang, atau satu gangguan jaringan
 * menghapus pengaturan yang sudah benar.
 */
class ReviewSumberEntitasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
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
            'name' => 'Holding', 'email' => 'holding@contoh.test',
            'password' => bcrypt('x'), 'is_active' => true, 'entity_id' => null,
        ]);
        $user->assignRole('Super Admin');

        $this->actingAs($user);
        app(EntityContext::class)->forget();

        return $user;
    }

    /* ───────────────────────────── Tombol Uji ───────────────────────────── */

    public function test_testing_an_unconfigured_entity_never_turns_it_into_a_local_source(): void
    {
        Config::set('bsc.require_entity_sources', true);
        $this->loginHolding();
        $aej = $this->entitas('AEJ');

        Livewire::test(EntitySources::class)->call('test', $aej->id);

        // Uji hanya melaporkan. Kalau ia sampai membuat baris sumber, holding
        // akan membaca DATABASENYA SENDIRI lalu menampilkan angkanya sebagai
        // angka AEJ — persis keadaan yang hendak dicegah mode ketat.
        $this->assertSame(0, EntityDataSource::count());
        $this->assertSame('belum diatur', app(EntitySourceFactory::class)->describe($aej));

        $ringkasan = app(EntitySourceFactory::class)->for($aej)->summary($aej, '2026-08');
        $this->assertSame(EntitySummary::STATUS_GALAT, $ringkasan->status);
    }

    public function test_testing_only_updates_the_status_of_an_existing_source(): void
    {
        $this->loginHolding();
        $aej = $this->entitas('AEJ');

        EntityDataSource::create([
            'entity_id' => $aej->id, 'driver' => EntityDataSource::API,
            'api_url' => 'https://bsc.aej.co.id', 'api_key' => 'kunci-lama',
        ]);

        Http::fake(['bsc.aej.co.id/*' => Http::response(['data' => [
            'entity' => 'AEJ', 'entity_code' => 'AEJ', 'version' => '1',
        ]], 200)]);

        Livewire::test(EntitySources::class)->call('test', $aej->id);

        $sumber = EntityDataSource::first();
        $this->assertSame('ok', $sumber->last_status);
        // Alamat & kuncinya tidak ikut ditulis ulang oleh tombol Uji.
        $this->assertSame('https://bsc.aej.co.id', $sumber->api_url);
        $this->assertSame('kunci-lama', $sumber->api_key);
    }

    /* ──────────────────── Jawaban entitas yang tidak beres ──────────────────── */

    public function test_a_malformed_entity_answer_does_not_bring_down_the_holding_page(): void
    {
        $this->loginHolding();

        // Jawaban dengan baris rasio tanpa 'name', capaian berupa teks, satuan
        // unit kerja yang bukan angka, dan waktu yang tidak dapat dibaca.
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://bsc.aej.co.id', 'api_key' => 'k', 'database' => null]);
        Http::fake(['bsc.aej.co.id/*' => Http::response(['data' => [
            'code' => 'AEJ', 'name' => 'AEJ', 'period' => '2026-08',
            'f1' => 'entah', 'f2' => 90, 'apex' => null,
            'revenue_target' => ['bukan angka'], 'revenue_actual' => '1000',
            'ratios' => [['code' => 'R1', 'achievement' => 'tidak ada'], 'bukan baris'],
            'units' => [['code' => 'U1', 'objectives' => 'banyak']],
            'fetched_at' => 'bukan tanggal',
        ]], 200)]);

        $ringkasan = app(EntitySourceFactory::class)->for($this->entitas('AEJ'))->summary($this->entitas('AEJ'), '2026-08');

        $this->assertNull($ringkasan->f1);                      // teks → kosong, bukan 0
        $this->assertSame(0.0, $ringkasan->revenueTarget);
        $this->assertCount(1, $ringkasan->ratios);              // baris yang bukan array dibuang
        $this->assertSame('', $ringkasan->ratios[0]['name']);   // kolom yang hilang tetap ada
        $this->assertNull($ringkasan->ratios[0]['achievement']);
        $this->assertSame(0, $ringkasan->units[0]['objectives']);
        $this->assertNotNull($ringkasan->fetchedAt);

        // Dan halaman Konsolidasi tetap terbuka untuk semua orang.
        $this->get('/konsolidasi')->assertOk();
    }

    /* ─────────────────────── Eliminasi & entitas mati ─────────────────────── */

    public function test_eliminations_of_an_unreachable_entity_are_left_out(): void
    {
        $this->loginHolding();

        IntercompanySale::create([
            'period' => '2026-08',
            'seller_entity_id' => $this->entitas('HERBATECH')->id,
            'buyer_entity_id' => $this->entitas('ERDIGMA')->id,
            'planned_amount' => 1_000_000, 'actual_amount' => 1_000_000,
        ]);

        // Herbatech tidak terjangkau: revenuenya tidak ikut terjumlah, jadi
        // eliminasinya pun tidak boleh ikut mengurangi.
        Config::set('bsc.sources.HERBATECH', ['api_url' => 'https://bsc.mati.test', 'api_key' => 'k', 'database' => null]);
        Http::fake(['bsc.mati.test/*' => Http::response('', 500)]);

        $hasil = app(Consolidation::class)->forPeriod('2026-08');

        $this->assertContains('HERBATECH', $hasil['group']['unreachable']);
        $this->assertSame(0.0, $hasil['group']['elimination_actual']);
        $this->assertSame(1, $hasil['group']['eliminations_skipped']);
        $this->assertSame(
            $hasil['group']['revenue_actual_gross'],
            $hasil['group']['revenue_actual_net']
        );
    }

    /* ───────────────────────── Kredensial & kunci API ───────────────────────── */

    public function test_unreadable_credentials_ask_to_be_set_again_instead_of_failing(): void
    {
        $this->loginHolding();
        $aej = $this->entitas('AEJ');

        EntityDataSource::create([
            'entity_id' => $aej->id, 'driver' => EntityDataSource::API,
            'api_url' => 'https://bsc.aej.co.id', 'api_key' => 'k',
        ]);

        // Kunci aplikasi berganti (atau database holding dipulihkan ke pemasangan
        // lain): nilai terenkripsinya tidak dapat dibuka lagi.
        DB::table('entity_sources')->update(['api_key' => 'bukan-hasil-enkripsi']);
        app(EntitySourceSettings::class)->forget();

        $this->assertSame('perlu diatur ulang', app(EntitySourceFactory::class)->describe($aej));
        $this->assertSame(EntitySummary::STATUS_GALAT,
            app(EntitySourceFactory::class)->for($aej)->summary($aej, '2026-08')->status);

        $this->get('/sumber-entitas')->assertOk();
        $this->get('/konsolidasi')->assertOk();
    }

    public function test_a_mistyped_address_list_refuses_the_call_instead_of_failing(): void
    {
        Config::set('bsc.holding_mode', false);
        Config::set('bsc.default_entity', 'ERDIGMA');
        app(EntityContext::class)->forget();

        // /64 tidak berlaku untuk alamat IPv4 — salah ketik yang mudah terjadi.
        // Dulu ini melempar ArithmeticError: SETIAP panggilan API jadi galat 500.
        [, $utuh] = ApiKey::issue('Holding', 'ERDIGMA', '10.8.0.0/64');

        $this->withServerVariables(['REMOTE_ADDR' => '10.8.0.5'])
            ->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping')->assertStatus(403);
    }

    public function test_an_ipv6_address_list_is_matched_properly(): void
    {
        Config::set('bsc.holding_mode', false);
        Config::set('bsc.default_entity', 'ERDIGMA');
        app(EntityContext::class)->forget();

        [, $utuh] = ApiKey::issue('Holding', 'ERDIGMA', '2001:db8::/32, 0:0:0:0:0:0:0:1');

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1234::9'])
            ->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping')->assertOk();

        // Bentuk singkat dan bentuk panjang alamat yang sama dianggap sama.
        $this->withServerVariables(['REMOTE_ADDR' => '::1'])
            ->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping')->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '2001:dead::1'])
            ->withHeader('X-API-KEY', $utuh)->getJson('/api/v1/ping')->assertStatus(403);
    }

    public function test_the_access_trail_is_pruned(): void
    {
        Config::set('bsc.api_log_days', 30);

        ApiAccessLog::create(['prefix' => 'lama', 'ip' => '10.0.0.1', 'path' => 'api/v1/ping',
            'status' => 401, 'result' => ApiAccessLog::KUNCI_SALAH, 'created_at' => now()->subDays(40)]);
        ApiAccessLog::create(['prefix' => 'baru', 'ip' => '10.0.0.1', 'path' => 'api/v1/ping',
            'status' => 401, 'result' => ApiAccessLog::KUNCI_SALAH, 'created_at' => now()->subDay()]);

        Artisan::call('model:prune', ['--model' => [ApiAccessLog::class]]);

        $this->assertSame(['baru'], ApiAccessLog::pluck('prefix')->all());
    }

    /* ─────────────────────────── Akses halaman holding ─────────────────────────── */

    public function test_a_holding_with_a_single_active_entity_can_still_open_its_pages(): void
    {
        $this->loginHolding();
        Entity::where('code', '!=', 'ERDIGMA')->update(['is_active' => false]);

        // Tidak ada entitas lain untuk berpindah, tetapi ini tetap pemasangan
        // holding — halaman holdingnya harus tetap terbuka.
        $this->assertFalse(can_switch_entity());
        $this->assertTrue(is_holding_user());
        $this->get('/konsolidasi')->assertOk();

        // Pemeriksaan pendaftaran dipicu peramban sesudah halaman tampil, bukan
        // saat halaman digambar — membuka halaman tidak boleh mengubah data.
        $this->get('/sumber-entitas')->assertOk()
            ->assertSee('wire:init="verifikasiPendaftaranBaru"', false);
    }

    public function test_changing_a_source_clears_the_stored_summary_of_every_period(): void
    {
        $this->loginHolding();
        $aej = $this->entitas('AEJ');

        Config::set('bsc.sources.AEJ', ['api_url' => 'https://bsc.aej.co.id', 'api_key' => 'k', 'database' => null]);
        Cache::put(Consolidation::cacheKey($aej, '2026-07'), ['code' => 'AEJ', 'f1' => 11], 600);
        Cache::put(Consolidation::cacheKey($aej, '2026-08'), ['code' => 'AEJ', 'f1' => 22], 600);

        app(Consolidation::class)->refreshAll();

        // Yang berubah bukan angka satu bulan, melainkan dari mana angkanya
        // diambil — simpanan periode lain pun tidak berlaku lagi.
        $this->assertNull(Cache::get(Consolidation::cacheKey($aej, '2026-07')));
        $this->assertNull(Cache::get(Consolidation::cacheKey($aej, '2026-08')));
    }
}
