<?php

namespace Tests\Feature;

use App\Livewire\StagingLogs;
use App\Models\AccountMapping;
use App\Models\Entity;
use App\Models\Period;
use App\Models\StagingLog;
use App\Support\Bsc\Integration\AccountIntake;
use App\Support\EntityContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Staging & Audit Log: jejak setiap data yang masuk ke entitas ini.
 *
 * Yang dijaga di sini adalah sifat yang membuat sebuah jejak audit berguna —
 * kapan kejadiannya terlihat, kegagalan ikut tercatat, dan halamannya sendiri
 * tidak dapat mengarang isinya.
 */
class StagingLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(EntityContext::class)->use((int) Entity::where('code', 'HERBATECH')->value('id'));
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
    }

    private function catat(string $unit, string $status, string $pesan, ?string $kapan = null): StagingLog
    {
        $log = StagingLog::create([
            'period' => '2026-08', 'dept_code' => $unit,
            'idempotency_key' => 'IDEMP-'.$unit.'-'.$status.'-'.uniqid(),
            'status' => $status, 'source_version' => 1, 'message' => $pesan,
        ]);

        if ($kapan) {
            $log->forceFill(['created_at' => $kapan])->save();
        }

        return $log;
    }

    public function test_the_table_shows_when_each_delivery_arrived(): void
    {
        $this->catat('FIN', 'SCORED', 'Odoo: 4 pos akun diperbarui.');

        // Waktu adalah kolom terpenting sebuah jejak audit; sebelumnya tabelnya
        // memuat periode, unit, penanda, status, dan pesan — tetapi tidak sekali
        // pun menyebutkan KAPAN kejadiannya.
        Livewire::test(StagingLogs::class)
            ->assertSee(now()->translatedFormat('d M Y'))
            ->assertSee('Waktu');
    }

    public function test_a_refused_delivery_is_recorded_so_it_can_be_traced(): void
    {
        $hasil = app(AccountIntake::class)->apply('2026-08', [
            ['code' => '9-99999', 'amount' => 5_000_000],
        ], ['source' => 'Odoo', 'idempotency_key' => 'IDEMP-GAGAL']);

        $this->assertFalse($hasil->ok());

        $jejak = StagingLog::sole();
        $this->assertSame('ERROR', $jejak->status);
        $this->assertStringContainsString('Odoo ditolak', $jejak->message);

        Livewire::test(StagingLogs::class)
            ->assertSee('Ditolak')
            ->assertSee('Lengkapi Pemetaan Akun');
    }

    public function test_a_refusal_does_not_burn_the_original_key(): void
    {
        // Penolakan memakai penanda tersendiri, sehingga kiriman ulang dengan
        // penanda yang sama masih diterima — kalau tidak, satu kegagalan akan
        // memblokir percobaan berikutnya selamanya.
        app(AccountIntake::class)->apply('2026-08', [['code' => '9-99999', 'amount' => 5_000_000]],
            ['idempotency_key' => 'IDEMP-ULANG']);

        AccountMapping::create(['source_code' => '4-10001', 'post_code' => 'PA01', 'invert' => true]);

        $kedua = app(AccountIntake::class)->apply('2026-08', [['code' => '4-10001', 'amount' => -812_000_000]],
            ['idempotency_key' => 'IDEMP-ULANG']);

        $this->assertTrue($kedua->ok(), $kedua->message);
    }

    public function test_the_summary_counts_things_that_can_actually_change(): void
    {
        $ringkasan = fn () => Livewire::test(StagingLogs::class)->viewData('ringkasan');

        $awal = $ringkasan();
        $this->assertSame('0', $awal['masuk']['nilai']);
        $this->assertSame('0', $awal['gagal']['nilai']);
        $this->assertSame('belum ada', $awal['terakhir']['nilai']);

        $this->catat('FIN', 'SCORED', 'diterima');
        $this->catat('FIN', 'ERROR', 'ditolak');
        // Kiriman lama tidak boleh ikut terhitung sebagai "7 hari terakhir".
        $this->catat('FIN', 'SCORED', 'lama sekali', now()->subDays(40)->toDateTimeString());

        $sekarang = $ringkasan();
        $this->assertSame('2', $sekarang['masuk']['nilai']);
        $this->assertSame('1', $sekarang['gagal']['nilai']);
        $this->assertSame('danger', $sekarang['gagal']['nada']);
    }

    public function test_the_list_only_shows_this_entitys_deliveries(): void
    {
        $this->catat('FIN', 'SCORED', 'milik entitas ini');

        $lain = (int) Entity::where('code', 'ERDIGMA')->value('id');
        $log = StagingLog::withoutGlobalScopes()->make([
            'period' => '2026-08', 'dept_code' => 'FIN', 'idempotency_key' => 'IDEMP-ENTITAS-LAIN',
            'status' => 'SCORED', 'source_version' => 1, 'message' => 'milik entitas lain',
        ]);
        $log->forceFill(['entity_id' => $lain])->save();

        Livewire::test(StagingLogs::class)
            ->assertSee('milik entitas ini')
            ->assertDontSee('milik entitas lain');
    }

    public function test_filtering_returns_to_the_first_page(): void
    {
        foreach (range(1, 30) as $i) {
            $this->catat('FIN', 'SCORED', 'kiriman ke-'.$i);
        }
        $this->catat('MFG', 'ERROR', 'satu-satunya yang ditolak');

        // Menyaring sambil berada di halaman 2 akan menampilkan halaman kosong
        // bila nomor halamannya tidak ikut dikembalikan.
        Livewire::test(StagingLogs::class)
            ->call('gotoPage', 2)
            ->assertViewHas('logs', fn ($l) => $l->currentPage() === 2)
            ->set('status', 'ERROR')
            ->assertViewHas('logs', fn ($l) => $l->currentPage() === 1 && $l->total() === 1)
            ->assertSee('satu-satunya yang ditolak');
    }
}
