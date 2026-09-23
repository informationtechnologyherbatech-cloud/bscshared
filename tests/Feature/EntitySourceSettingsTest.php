<?php

namespace Tests\Feature;

use App\Livewire\EntitySources;
use App\Models\Entity;
use App\Models\EntityDataSource;
use App\Models\User;
use App\Support\Bsc\Consolidation;
use App\Support\Bsc\EntitySummary;
use App\Support\Bsc\Sources\ApiEntitySource;
use App\Support\Bsc\Sources\EntitySourceFactory;
use App\Support\Bsc\Sources\EntitySourceSettings;
use App\Support\EntityContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sumber data tiap entitas diatur dari layar holding (Sumber Data Entitas),
 * bukan dengan menyunting .env di server. Kunci API tersimpan terenkripsi dan
 * pengaturan layar mengalahkan nilai .env.
 */
class EntitySourceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Config::set('bsc.holding_mode', true);
    }

    private function entitas(string $kode): Entity
    {
        return Entity::where('code', $kode)->firstOrFail();
    }

    private function loginHolding(string $peran = 'Super Admin'): User
    {
        $user = User::create([
            'name' => $peran, 'email' => str_replace(' ', '', strtolower($peran)).'-'.uniqid().'@contoh.test',
            'password' => bcrypt('x'), 'is_active' => true, 'entity_id' => null,
        ]);
        $user->assignRole($peran);
        $this->actingAs($user);
        app(EntityContext::class)->forget();

        return $user;
    }

    public function test_a_source_saved_on_screen_is_used_and_beats_the_env_file(): void
    {
        $this->loginHolding();
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://dari-env.test', 'api_key' => 'kunci-env', 'database' => null]);

        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->assertSet('driver', 'api')
            ->assertSet('apiUrl', 'https://dari-env.test') // isian diawali nilai .env
            ->set('apiUrl', 'https://bsc.aej.co.id/')
            ->set('apiKey', 'bsc_live_dari_layar')
            ->call('save')
            ->assertHasNoErrors();

        $sumber = app(EntitySourceSettings::class)->for($this->entitas('AEJ'));
        $this->assertSame('https://bsc.aej.co.id', $sumber['api_url']); // garis miring di ujung dirapikan
        $this->assertSame('bsc_live_dari_layar', $sumber['api_key']);
        $this->assertSame('layar', $sumber['origin']);
        $this->assertInstanceOf(ApiEntitySource::class, app(EntitySourceFactory::class)->for($this->entitas('AEJ')));
    }

    public function test_the_api_key_is_stored_encrypted_and_never_shown_in_full(): void
    {
        $this->loginHolding();

        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->set('driver', 'api')
            ->set('apiUrl', 'https://bsc.aej.co.id')
            ->set('apiKey', 'bsc_live_rahasia_sekali')
            ->call('save');

        $mentah = (string) DB::table('entity_sources')->where('entity_id', $this->entitas('AEJ')->id)->value('api_key');
        $this->assertNotSame('bsc_live_rahasia_sekali', $mentah);
        $this->assertStringNotContainsString('rahasia', $mentah);
        $this->assertSame('bsc_live_rahasia_sekali', EntityDataSource::first()->api_key);

        // Di layar hanya ujungnya yang tampil.
        Livewire::test(EntitySources::class)
            ->assertDontSee('bsc_live_rahasia_sekali')
            ->assertSee('••••••••kali');
    }

    public function test_an_existing_key_is_kept_when_the_field_is_left_empty(): void
    {
        $this->loginHolding();
        EntityDataSource::create([
            'entity_id' => $this->entitas('AEJ')->id, 'driver' => 'api',
            'api_url' => 'https://bsc.aej.co.id', 'api_key' => 'kunci-lama',
        ]);

        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->assertSet('apiKey', '')        // kunci lama tidak pernah dimuat ke layar
            ->assertSet('punyaKunci', true)
            ->set('apiUrl', 'https://bsc-baru.aej.co.id')
            ->call('save')
            ->assertHasNoErrors();

        $baris = EntityDataSource::first();
        $this->assertSame('https://bsc-baru.aej.co.id', $baris->api_url);
        $this->assertSame('kunci-lama', $baris->api_key);
    }

    public function test_an_api_source_without_address_or_key_is_refused(): void
    {
        $this->loginHolding();

        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->set('driver', 'api')->set('apiUrl', '')->set('apiKey', '')
            ->call('save')
            ->assertHasErrors(['apiUrl', 'apiKey']);

        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->set('driver', 'database')->set('databaseName', '')
            ->call('save')
            ->assertHasErrors('databaseName');
    }

    public function test_changing_the_source_drops_the_cached_summary(): void
    {
        $this->loginHolding();
        Config::set('bsc.sources.AEJ', ['api_url' => 'https://lama.test', 'api_key' => 'k', 'database' => null]);
        Http::fake(['*' => Http::response(['data' => ['code' => 'AEJ', 'period' => '2026-08', 'f1' => 10, 'f2' => 10, 'apex' => 10]], 200)]);
        app(Consolidation::class)->forPeriod('2026-08');

        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->set('driver', 'api')->set('apiUrl', 'https://baru.test')->set('apiKey', 'k2')
            ->call('save');

        app(Consolidation::class)->forPeriod('2026-08');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'baru.test'));
    }

    public function test_testing_the_connection_records_the_result(): void
    {
        $this->loginHolding();
        EntityDataSource::create([
            'entity_id' => $this->entitas('AEJ')->id, 'driver' => 'api',
            'api_url' => 'https://bsc.aej.co.id', 'api_key' => 'kunci',
        ]);

        Http::fake(['bsc.aej.co.id/*' => Http::response(['data' => [
            'entity' => 'AEJ', 'entity_code' => 'AEJ', 'app' => 'Super Apps BSC', 'version' => 'v1.0.0', 'time' => now()->toIso8601String(),
        ]], 200)]);

        Livewire::test(EntitySources::class)->call('test', $this->entitas('AEJ')->id);

        $baris = EntityDataSource::first();
        $this->assertSame('ok', $baris->last_status);
        $this->assertStringContainsString('tersambung', $baris->last_message);
        $this->assertNotNull($baris->last_checked_at);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v1/ping') && $r->hasHeader('X-API-KEY', 'kunci'));
    }

    public function test_a_wrong_key_or_wrong_entity_is_reported_clearly(): void
    {
        $this->loginHolding();
        EntityDataSource::create([
            'entity_id' => $this->entitas('AEJ')->id, 'driver' => 'api',
            'api_url' => 'https://bsc.aej.co.id', 'api_key' => 'kunci-salah',
        ]);

        Http::fake(['bsc.aej.co.id/*' => Http::sequence()
            ->push(['message' => 'ditolak'], 401)
            ->push(['data' => ['entity' => 'Herbatech', 'entity_code' => 'HERBATECH', 'version' => 'v1']], 200)]);

        Livewire::test(EntitySources::class)->call('test', $this->entitas('AEJ')->id);
        $this->assertStringContainsString('kunci API ditolak', EntityDataSource::first()->last_message);

        Livewire::test(EntitySources::class)->call('test', $this->entitas('AEJ')->id);
        $this->assertStringContainsString('melayani entitas HERBATECH', EntityDataSource::first()->last_message);
        $this->assertSame('galat', EntityDataSource::first()->last_status);
    }

    public function test_only_holding_users_may_open_or_change_the_sources(): void
    {
        // Pengguna yang terikat satu entitas: tidak boleh membuka halamannya.
        $terikat = User::create([
            'name' => 'Admin Erdigma', 'email' => 'terikat@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => $this->entitas('ERDIGMA')->id,
        ]);
        $terikat->assignRole('Super Admin');
        $this->actingAs($terikat);
        app(EntityContext::class)->forget();
        $this->get('/sumber-entitas')->assertForbidden();

        // Peran tanpa izin konsolidasi sama sekali: halamannya tertutup.
        $this->loginHolding('Viewer');
        $this->get('/sumber-entitas')->assertForbidden();

        // Boleh melihat (view consolidation) tetapi tidak boleh mengubah.
        $pengawas = Role::create(['name' => 'Pengawas Holding']);
        $pengawas->givePermissionTo('view consolidation');
        $this->loginHolding('Pengawas Holding');
        $this->get('/sumber-entitas')->assertOk();

        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->set('driver', 'database')->set('databaseName', 'db_coba')
            ->call('save');
        $this->assertNull(EntityDataSource::first()); // tanpa manage consolidation, tidak tersimpan

        // Admin FAT & Super Admin memang berhak mengubah.
        $this->loginHolding('Admin FAT');
        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->set('driver', 'database')->set('databaseName', 'db_bsc_aej')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('db_bsc_aej', EntityDataSource::first()->database_name);
    }

    public function test_choosing_local_on_screen_is_respected_in_strict_mode(): void
    {
        $this->loginHolding();
        Config::set('bsc.require_entity_sources', true);

        // Belum diatur: ditandai galat.
        $aej = collect(app(Consolidation::class)->forPeriod('2026-08')['entities'])->firstWhere('entity.code', 'AEJ');
        $this->assertSame(EntitySummary::STATUS_GALAT, $aej['status']);

        // Dipilih "lokal" secara sadar di layar: dihormati.
        Livewire::test(EntitySources::class)
            ->call('edit', $this->entitas('AEJ')->id)
            ->set('driver', 'lokal')
            ->call('save');

        $aej = collect(app(Consolidation::class)->forPeriod('2026-08')['entities'])->firstWhere('entity.code', 'AEJ');
        $this->assertSame(EntitySummary::STATUS_OK, $aej['status']);
        $this->assertSame(EntitySummary::SUMBER_LOKAL, $aej['source']);
    }
}
