<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\RevenueTarget;
use App\Models\User;
use App\Support\Bsc\Consolidation;
use App\Support\Bsc\EntitySummary;
use App\Support\Bsc\Sources\ApiEntitySource;
use App\Support\Bsc\Sources\DatabaseEntitySource;
use App\Support\Bsc\Sources\EntitySourceFactory;
use App\Support\Bsc\Sources\LocalEntitySource;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Database per entitas: data satu entitas tidak dapat dibaca entitas lain.
 * Holding (EMC) tidak menyimpan data entitas — ia meminta RINGKASAN ke sumber
 * tiap entitas (database entitas atau API-nya) saat halaman Konsolidasi dibuka.
 */
class EntityIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class); // contoh Erdigma 2026-08
    }

    private function entitas(string $kode): Entity
    {
        return Entity::where('code', $kode)->firstOrFail();
    }

    private function kunciApi(string $kunci = 'bsc_live_uji'): ApiKey
    {
        return ApiKey::create(['name' => 'Uji', 'key' => $kunci, 'is_active' => true]);
    }

    /* ─────────────────────────── API di sisi entitas ─────────────────────────── */

    public function test_the_entity_api_needs_an_active_key(): void
    {
        Config::set('bsc.holding_mode', false);
        $this->kunciApi();

        $this->getJson('/api/v1/consolidation?period=2026-08')->assertStatus(401);
        $this->withHeader('X-API-KEY', 'salah')->getJson('/api/v1/consolidation?period=2026-08')->assertStatus(401);

        ApiKey::query()->update(['is_active' => false]);
        $this->withHeader('X-API-KEY', 'bsc_live_uji')->getJson('/api/v1/consolidation?period=2026-08')->assertStatus(401);
    }

    public function test_the_entity_api_only_serves_its_own_entity_and_only_a_summary(): void
    {
        Config::set('bsc.holding_mode', false);
        Config::set('bsc.default_entity', 'ERDIGMA');
        app(EntityContext::class)->forget();
        $this->kunciApi();

        // Kode entitas lain pada permintaan diabaikan — tetap entitas pemasangan ini.
        $respons = $this->withHeader('X-API-KEY', 'bsc_live_uji')
            ->getJson('/api/v1/consolidation?period=2026-08&entity=AEJ')
            ->assertOk();

        $data = $respons->json('data');
        $this->assertSame('ERDIGMA', $data['code']);
        $this->assertSame('2026-08', $data['period']);
        $this->assertSame(94.1, $data['f2']);
        $this->assertCount(19, $data['ratios']);
        $this->assertNotEmpty($data['units']);

        // Hanya ringkasan: tidak ada pos akun, isi sasaran mutu, atau program kerja.
        foreach (['account_balances', 'objectives_detail', 'action_plans', 'kpi_cascades', 'users'] as $terlarang) {
            $this->assertArrayNotHasKey($terlarang, $data);
        }
        $this->assertSame(['code', 'name', 'objectives', 'score', 'status'], array_keys($data['units'][0]));
    }

    public function test_a_holding_installation_does_not_serve_the_entity_api(): void
    {
        Config::set('bsc.holding_mode', true);
        app(EntityContext::class)->forget();
        $this->kunciApi();

        $this->withHeader('X-API-KEY', 'bsc_live_uji')
            ->getJson('/api/v1/consolidation?period=2026-08')
            ->assertStatus(409);
    }

    public function test_the_ping_endpoint_exposes_no_performance_figures(): void
    {
        Config::set('bsc.holding_mode', false);
        $this->kunciApi();

        $data = $this->withHeader('X-API-KEY', 'bsc_live_uji')->getJson('/api/v1/ping')->assertOk()->json('data');

        $this->assertSame(['entity', 'entity_code', 'app', 'version', 'time'], array_keys($data));
    }

    /* ─────────────────────────── Pemilihan sumber data ─────────────────────────── */

    public function test_the_source_of_each_entity_follows_the_configuration(): void
    {
        $pabrik = app(EntitySourceFactory::class);

        Config::set('bsc.sources.AEJ', ['api_url' => null, 'api_key' => null, 'database' => null]);
        $this->assertInstanceOf(LocalEntitySource::class, $pabrik->for($this->entitas('AEJ')));

        Config::set('bsc.sources.AEJ', ['api_url' => null, 'api_key' => null, 'database' => 'db_bsc_aej']);
        $this->assertInstanceOf(DatabaseEntitySource::class, $pabrik->for($this->entitas('AEJ')));
        $this->assertSame('database db_bsc_aej', $pabrik->describe($this->entitas('AEJ')));

        // API menang bila keduanya diisi.
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://bsc.aej.co.id', 'api_key' => 'k', 'database' => 'db_bsc_aej']);
        $this->assertInstanceOf(ApiEntitySource::class, $pabrik->for($this->entitas('AEJ')));
        $this->assertSame('API bsc.aej.co.id', $pabrik->describe($this->entitas('AEJ')));
    }

    /* ─────────────────────────── Holding membaca lewat API ─────────────────────────── */

    private function jawabanApi(array $ganti = []): array
    {
        return ['data' => array_merge([
            'code' => 'AEJ', 'name' => 'AEJ', 'legal_name' => 'PT Abithama Emas Juara', 'industry' => 'Manufaktur',
            'period' => '2026-08', 'f1' => 80.0, 'f2' => 90.0, 'apex' => 85.5,
            'revenue_target' => 1000.0, 'revenue_actual' => 800.0,
            'objectives' => 5, 'objective_score' => 88.0, 'kpi_total' => 4, 'kpi_approved' => 3,
            'ratios' => [['code' => 'P1', 'name' => 'GPM', 'unit' => '%', 'target' => 37, 'actual' => 35, 'achievement' => 94.6, 'status' => 'Waspada']],
            'units' => [['code' => 'OPS', 'name' => 'Operasional', 'objectives' => 5, 'score' => 88.0, 'status' => 'waspada']],
        ], $ganti)];
    }

    public function test_holding_reads_a_remote_entity_through_its_api_without_storing_it(): void
    {
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://bsc.aej.co.id', 'api_key' => 'rahasia', 'database' => null]);
        Http::fake(['bsc.aej.co.id/*' => Http::response($this->jawabanApi(), 200)]);

        $sebelum = ['ratios' => FinancialRatio::withoutGlobalScopes()->count(), 'revenue' => RevenueTarget::withoutGlobalScopes()->count()];
        $hasil = app(Consolidation::class)->forPeriod('2026-08');
        $aej = collect($hasil['entities'])->firstWhere('entity.code', 'AEJ');

        $this->assertSame(EntitySummary::SUMBER_API, $aej['source']);
        $this->assertSame(80.0, $aej['f1']);
        $this->assertSame(90.0, $aej['f2']);
        $this->assertCount(1, $aej['ratios']);

        // Kunci API ikut dikirim, dan tidak satu baris pun disimpan di database holding.
        Http::assertSent(fn ($r) => $r->hasHeader('X-API-KEY', 'rahasia') && str_contains($r->url(), 'period=2026-08'));
        $this->assertSame($sebelum['ratios'], FinancialRatio::withoutGlobalScopes()->count());
        $this->assertSame($sebelum['revenue'], RevenueTarget::withoutGlobalScopes()->count());
    }

    public function test_a_remote_entity_that_is_down_is_empty_not_zero(): void
    {
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://bsc.aej.co.id', 'api_key' => 'rahasia', 'database' => null]);
        Http::fake(['bsc.aej.co.id/*' => Http::response('', 500)]);

        $hasil = app(Consolidation::class)->forPeriod('2026-08');
        $aej = collect($hasil['entities'])->firstWhere('entity.code', 'AEJ');

        $this->assertSame(EntitySummary::STATUS_GALAT, $aej['status']);
        $this->assertNull($aej['apex']);
        $this->assertSame(['AEJ'], $hasil['group']['unreachable']);
    }

    public function test_a_server_that_serves_another_entity_is_refused(): void
    {
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://salah-pasang.test', 'api_key' => 'rahasia', 'database' => null]);
        Http::fake(['salah-pasang.test/*' => Http::response($this->jawabanApi(['code' => 'HERBATECH']), 200)]);

        $aej = collect(app(Consolidation::class)->forPeriod('2026-08')['entities'])->firstWhere('entity.code', 'AEJ');

        $this->assertSame(EntitySummary::STATUS_GALAT, $aej['status']);
        $this->assertStringContainsString('melayani entitas HERBATECH', (string) $aej['message']);
    }

    public function test_remote_summaries_are_cached_and_can_be_refreshed(): void
    {
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://bsc.aej.co.id', 'api_key' => 'rahasia', 'database' => null]);
        Config::set('bsc.consolidation_ttl', 300);
        Http::fake(['bsc.aej.co.id/*' => Http::response($this->jawabanApi(), 200)]);

        $konsolidasi = app(Consolidation::class);
        $konsolidasi->forPeriod('2026-08');
        $konsolidasi->forPeriod('2026-08'); // dilayani cache
        Http::assertSentCount(1);

        $konsolidasi->refresh('2026-08');
        $konsolidasi->forPeriod('2026-08');
        Http::assertSentCount(2);
    }

    /* ─────────────────────── Holding membaca database entitas ─────────────────────── */

    public function test_holding_reads_an_entity_that_lives_in_its_own_database(): void
    {
        $berkas = storage_path('framework/testing/entitas_aej_uji.sqlite');
        @unlink($berkas);
        touch($berkas);

        // Database terpisah milik AEJ: dimigrasikan & diisi sendiri.
        Config::set('database.connections.aej_uji', ['driver' => 'sqlite', 'database' => $berkas, 'prefix' => '', 'foreign_key_constraints' => true]);
        Artisan::call('migrate', ['--database' => 'aej_uji', '--force' => true]);

        $aejId = DB::connection('aej_uji')->table('entities')->where('code', 'AEJ')->value('id');
        DB::connection('aej_uji')->table('periods')->insert(['entity_id' => $aejId, 'period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['2026-07', 100, 100], ['2026-08', 100, 90]] as [$p, $t, $a]) {
            DB::connection('aej_uji')->table('revenue_targets')->insert(['entity_id' => $aejId, 'period' => $p, 'target' => $t, 'actual' => $a, 'created_at' => now(), 'updated_at' => now()]);
        }

        Config::set('bsc.sources.AEJ', ['api_url' => null, 'api_key' => null, 'database' => $berkas]);
        $hasil = app(Consolidation::class)->forPeriod('2026-08');
        $aej = collect($hasil['entities'])->firstWhere('entity.code', 'AEJ');

        $this->assertSame(EntitySummary::SUMBER_DATABASE, $aej['source']);
        $this->assertSame(95.0, $aej['f1']); // 190 ÷ 200 dari database AEJ
        $this->assertSame(200.0, $aej['revenue_target']);

        // Database holding tidak ikut kemasukan data AEJ, dan konteks entitas pulih.
        $this->assertSame(0, RevenueTarget::withoutGlobalScopes()->where('entity_id', $this->entitas('AEJ')->id)->count());
        $this->assertSame(config('database.default'), DB::getDefaultConnection());

        @unlink($berkas);
    }

    public function test_an_unreadable_entity_database_does_not_break_the_page(): void
    {
        Config::set('bsc.sources.AEJ', ['api_url' => null, 'api_key' => null, 'database' => storage_path('framework/testing/tidak-ada.sqlite')]);

        $aej = collect(app(Consolidation::class)->forPeriod('2026-08')['entities'])->firstWhere('entity.code', 'AEJ');

        $this->assertSame(EntitySummary::STATUS_GALAT, $aej['status']);
        $this->assertNull($aej['f1']);
    }

    /* ─────────────────────────── Batas antarentitas tetap ─────────────────────────── */

    public function test_an_entity_installation_never_sees_another_entity(): void
    {
        Config::set('bsc.holding_mode', false);
        Config::set('bsc.default_entity', 'ERDIGMA');
        $konteks = app(EntityContext::class);
        $konteks->forget();

        // Data Erdigma ada; data entitas lain tidak terlihat dari pemasangan ini.
        $this->assertGreaterThan(0, Period::count());
        $herbatech = $this->entitas('HERBATECH')->id;
        $this->assertSame(0, Period::query()->where('entity_id', $herbatech)->count());
    }

    public function test_pages_say_where_the_data_of_an_entity_actually_lives(): void
    {
        Config::set('bsc.holding_mode', true);
        $admin = User::create([
            'name' => 'Holding', 'email' => 'holding@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => null,
        ]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
        app(EntityContext::class)->use($this->entitas('ERDIGMA')->id);

        Config::set('bsc.sources.ERDIGMA', ['api_url' => null, 'api_key' => null, 'database' => null]);
        $this->assertFalse(entity_source_is_remote());
        $this->get('/')->assertOk()->assertDontSee('disimpan di sumbernya');

        Config::set('bsc.sources.ERDIGMA', ['api_url' => 'https://bsc.erdigma.co.id', 'api_key' => 'k', 'database' => null]);
        $this->assertTrue(entity_source_is_remote());
        $this->assertSame('API bsc.erdigma.co.id', entity_source_label());
        $this->get('/')->assertOk()->assertSee('disimpan di sumbernya');
        // Di Konsolidasi tidak perlu diingatkan: halaman itu memang mengambil dari sumbernya.
        $this->get('/konsolidasi')->assertOk()->assertDontSee('disimpan di sumbernya');
    }

    /* ─────────────────── Pengerasan dari review (kunci API & sumber) ─────────────────── */

    public function test_guessing_api_keys_is_rate_limited(): void
    {
        Config::set('bsc.holding_mode', false);
        $this->kunciApi();

        $status = [];
        for ($i = 0; $i < 65; $i++) {
            $status[] = $this->withHeader('X-API-KEY', 'tebakan-'.$i)->getJson('/api/v1/ping')->getStatusCode();
        }

        // Pembatas laju berjalan SEBELUM pemeriksaan kunci: menebak kunci ikut dibatasi.
        $this->assertContains(429, $status);
    }

    public function test_a_key_of_another_entity_is_refused_even_on_a_shared_database(): void
    {
        Config::set('bsc.holding_mode', false);
        Config::set('bsc.default_entity', 'ERDIGMA');
        app(EntityContext::class)->forget();
        ApiKey::create(['name' => 'Kunci AEJ', 'entity_code' => 'AEJ', 'key' => 'bsc_live_aej', 'is_active' => true]);

        $this->withHeader('X-API-KEY', 'bsc_live_aej')->getJson('/api/v1/consolidation')->assertStatus(403);

        // Kunci milik entitas pemasangan ini tetap dilayani.
        ApiKey::create(['name' => 'Kunci Erdigma', 'entity_code' => 'ERDIGMA', 'key' => 'bsc_live_erd', 'is_active' => true]);
        $this->withHeader('X-API-KEY', 'bsc_live_erd')->getJson('/api/v1/consolidation')->assertOk();
    }

    public function test_an_answer_without_an_entity_code_is_refused(): void
    {
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://bsc.aej.co.id', 'api_key' => 'rahasia', 'database' => null]);
        $tanpaKode = $this->jawabanApi();
        unset($tanpaKode['data']['code']);
        Http::fake(['bsc.aej.co.id/*' => Http::response($tanpaKode, 200)]);

        $aej = collect(app(Consolidation::class)->forPeriod('2026-08')['entities'])->firstWhere('entity.code', 'AEJ');

        $this->assertSame(EntitySummary::STATUS_GALAT, $aej['status']);
        $this->assertNull($aej['f1']);
    }

    public function test_the_api_key_never_follows_a_redirect(): void
    {
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://bsc.aej.co.id', 'api_key' => 'rahasia', 'database' => null]);
        Http::fake([
            'bsc.aej.co.id/*' => Http::response('', 302, ['Location' => 'https://pemanen-kunci.test/ambil']),
            'pemanen-kunci.test/*' => Http::response($this->jawabanApi(), 200),
        ]);

        $aej = collect(app(Consolidation::class)->forPeriod('2026-08')['entities'])->firstWhere('entity.code', 'AEJ');

        $this->assertSame(EntitySummary::STATUS_GALAT, $aej['status']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'pemanen-kunci.test'));
    }

    public function test_an_unconfigured_source_is_refused_in_strict_holding_mode(): void
    {
        Config::set('bsc.holding_mode', true);
        Config::set('bsc.require_entity_sources', true);
        Config::set('bsc.sources.AEJ', ['api_url' => null, 'api_key' => null, 'database' => null]);
        app(EntityContext::class)->forget();

        $aej = collect(app(Consolidation::class)->forPeriod('2026-08')['entities'])->firstWhere('entity.code', 'AEJ');

        // Tanpa mode ketat, entitas ini akan diam-diam dibaca dari database holding.
        $this->assertSame(EntitySummary::STATUS_GALAT, $aej['status']);
        $this->assertStringContainsString('BSC_SOURCE_AEJ_URL', (string) $aej['message']);
    }

    public function test_cached_summaries_do_not_leak_between_installations(): void
    {
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://bsc.aej.co.id', 'api_key' => 'rahasia', 'database' => null]);
        $kunciSatu = Consolidation::cacheKey($this->entitas('AEJ'), '2026-08');

        // Pemasangan lain (entitas bawaan & alamat berbeda) memakai kunci cache berbeda,
        // walau berbagi satu penyimpanan cache.
        Config::set('bsc.default_entity', 'HERBATECH');
        Config::set('app.url', 'https://bsc.holding-lain.co.id');
        $this->assertNotSame($kunciSatu, Consolidation::cacheKey($this->entitas('AEJ'), '2026-08'));

        // Berganti sumber juga berganti kunci: data lama tidak ikut dilayani.
        Config::set('bsc.sources.AEJ', ['api_url' => null, 'api_key' => null, 'database' => 'db_bsc_aej']);
        $this->assertNotSame($kunciSatu, Consolidation::cacheKey($this->entitas('AEJ'), '2026-08'));
    }

    public function test_reading_another_database_keeps_the_chosen_entity(): void
    {
        $konteks = app(EntityContext::class);
        $konteks->use($this->entitas('ERDIGMA')->id);
        Config::set('bsc.sources.AEJ', ['api_url' => null, 'api_key' => null, 'database' => storage_path('framework/testing/tidak-ada.sqlite')]);

        app(Consolidation::class)->forPeriod('2026-08');

        // Pilihan entitas pemanggil tidak boleh hilang setelah membaca database lain.
        $this->assertSame($this->entitas('ERDIGMA')->id, $konteks->id());
        $this->assertSame(config('database.default'), DB::getDefaultConnection());
    }

    public function test_a_database_failure_does_not_leak_connection_details(): void
    {
        Config::set('bsc.sources.AEJ', ['api_url' => null, 'api_key' => null, 'database' => storage_path('framework/testing/tidak-ada.sqlite')]);

        $aej = collect(app(Consolidation::class)->forPeriod('2026-08')['entities'])->firstWhere('entity.code', 'AEJ');

        $this->assertStringNotContainsString('select', strtolower((string) $aej['message']));
        $this->assertStringNotContainsString('tidak-ada.sqlite', (string) $aej['message']);
    }
}
