<?php

namespace Tests\Feature;

use App\Livewire\OdooIntegration;
use App\Livewire\SystemIntegration;
use App\Models\AccountBalance;
use App\Models\AccountMapping;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\OdooConnection;
use App\Models\Period;
use App\Models\RevenueTarget;
use App\Models\StagingLog;
use App\Models\User;
use App\Support\Bsc\Integration\AccountIntake;
use App\Support\Bsc\Integration\CsvIntake;
use App\Support\Bsc\Integration\IntakeResult;
use App\Support\Bsc\Integration\OdooPuller;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Integrasi Odoo: dari bagan akun entitas sampai menjadi rasio keuangan.
 *
 * Yang dijaga di sini adalah hal-hal yang membuat angkanya benar — arti pos
 * aliran vs neraca, tanda saldo kredit, periode tertutup, dan kiriman kembar —
 * bukan sekadar "tidak galat".
 */
class IntegrasiOdooTest extends TestCase
{
    use RefreshDatabase;

    private int $entitas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entitas = (int) Entity::where('code', 'HERBATECH')->value('id');
        app(EntityContext::class)->use($this->entitas);

        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
    }

    private function petakan(string $kode, string $pos, bool $balik = false): void
    {
        AccountMapping::create(['source_code' => $kode, 'post_code' => $pos, 'invert' => $balik]);
    }

    /* ───────────────────────── Pemasukan pos akun ───────────────────────── */

    public function test_account_codes_are_translated_through_the_mapping(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);   // penjualan: kredit → dibalik
        $this->petakan('5-10001', 'PA02');

        $hasil = app(AccountIntake::class)->apply('2026-08', [
            ['code' => '4-10001', 'amount' => -812_000_000],
            ['code' => '5-10001', 'amount' => 430_000_000],
        ], ['idempotency_key' => 'IDEMP-UJI-1']);

        $this->assertTrue($hasil->ok());
        $this->assertSame(812_000_000.0, (float) AccountBalance::where('code', 'PA01')->value('amount'));
        $this->assertSame(430_000_000.0, (float) AccountBalance::where('code', 'PA02')->value('amount'));
    }

    public function test_several_accounts_may_feed_the_same_post(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);
        $this->petakan('4-10002', 'PA01', balik: true);

        app(AccountIntake::class)->apply('2026-08', [
            ['code' => '4-10001', 'amount' => -600_000_000],
            ['code' => '4-10002', 'amount' => -212_000_000],
        ], ['idempotency_key' => 'IDEMP-UJI-2']);

        $this->assertSame(812_000_000.0, (float) AccountBalance::where('code', 'PA01')->value('amount'));
    }

    public function test_an_opening_balance_is_kept_only_for_balance_sheet_posts(): void
    {
        $this->petakan('1-10002', 'PA08');   // kas — pos neraca
        $this->petakan('5-10001', 'PA02');   // HPP — pos aliran

        app(AccountIntake::class)->apply('2026-08', [
            ['code' => '1-10002', 'amount' => 95_000_000, 'opening' => 70_000_000],
            ['code' => '5-10001', 'amount' => 430_000_000, 'opening' => 12_000_000],
        ], ['idempotency_key' => 'IDEMP-UJI-3']);

        $this->assertSame(70_000_000.0, (float) AccountBalance::where('code', 'PA08')->value('opening'));
        // Saldo awal tidak bermakna bagi pos aliran; jangan sampai menggantung di sana.
        $this->assertNull(AccountBalance::where('code', 'PA02')->value('opening'));
    }

    public function test_unmapped_accounts_are_reported_instead_of_silently_dropped(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);

        $hasil = app(AccountIntake::class)->apply('2026-08', [
            ['code' => '4-10001', 'amount' => -812_000_000],
            ['code' => '9-99999', 'amount' => 5_000_000],
        ], ['idempotency_key' => 'IDEMP-UJI-4']);

        $this->assertTrue($hasil->ok());
        $this->assertSame(['9-99999'], $hasil->unmapped);
        $this->assertStringContainsString('belum dipetakan', $hasil->message);
    }

    public function test_without_any_mapping_nothing_is_written(): void
    {
        $hasil = app(AccountIntake::class)->apply('2026-08', [
            ['code' => '4-10001', 'amount' => -812_000_000],
        ], ['idempotency_key' => 'IDEMP-UJI-5']);

        $this->assertSame(IntakeResult::DITOLAK, $hasil->outcome);
        $this->assertSame(0, AccountBalance::count());

        // Penolakannya TERCATAT: jejak audit yang hanya memuat keberhasilan tidak
        // menjawab pertanyaan "kirimannya sampai atau tidak?".
        $jejak = StagingLog::sole();
        $this->assertSame('ERROR', $jejak->status);
        $this->assertStringContainsString('ditolak', $jejak->message);
        // Penandanya berbeda dari penanda aslinya, supaya kiriman ulang dengan
        // penanda yang sama nanti tetap dapat diterima.
        $this->assertNotSame('IDEMP-UJI-5', $jejak->idempotency_key);
        $this->assertStringStartsWith('IDEMP-UJI-5-TOLAK-', $jejak->idempotency_key);
    }

    public function test_the_ratios_are_recalculated_and_the_trail_is_written(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);
        $this->petakan('5-10001', 'PA02');

        $hasil = app(AccountIntake::class)->apply('2026-08', [
            ['code' => '4-10001', 'amount' => -812_000_000],
            ['code' => '5-10001', 'amount' => 430_000_000],
        ], ['source' => 'Odoo', 'idempotency_key' => 'IDEMP-UJI-6']);

        $this->assertGreaterThan(0, $hasil->ratios);
        $this->assertGreaterThan(0, FinancialRatio::where('period', '2026-08')->count());

        $jejak = StagingLog::first();
        $this->assertSame('SCORED', $jejak->status);
        $this->assertStringContainsString('Odoo', $jejak->message);
    }

    public function test_the_same_delivery_is_not_applied_twice(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);

        $kirim = fn () => app(AccountIntake::class)->apply('2026-08', [
            ['code' => '4-10001', 'amount' => -812_000_000],
        ], ['idempotency_key' => 'IDEMP-SAMA']);

        $this->assertTrue($kirim()->ok());
        $kedua = $kirim();

        $this->assertSame(IntakeResult::GANDA, $kedua->outcome);
        $this->assertSame(1, StagingLog::count());
    }

    public function test_a_closed_period_refuses_incoming_data(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);
        Period::where('period', '2026-08')->update(['status' => 'CLOSED']);

        $hasil = app(AccountIntake::class)->apply('2026-08', [
            ['code' => '4-10001', 'amount' => -812_000_000],
        ], ['idempotency_key' => 'IDEMP-TUTUP']);

        $this->assertSame(IntakeResult::DITOLAK, $hasil->outcome);
        $this->assertSame(0, AccountBalance::count());
    }

    public function test_revenue_actual_is_filled_without_touching_the_target(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);
        RevenueTarget::create(['period' => '2026-08', 'target' => 900_000_000, 'actual' => 0]);

        app(AccountIntake::class)->apply('2026-08', [
            ['code' => '4-10001', 'amount' => -812_000_000],
        ], ['idempotency_key' => 'IDEMP-REV', 'revenue' => 812_000_000]);

        $revenue = RevenueTarget::where('period', '2026-08')->first();
        $this->assertSame(812_000_000.0, (float) $revenue->actual);
        // Target adalah hasil perencanaan, bukan hasil pencatatan.
        $this->assertSame(900_000_000.0, (float) $revenue->target);
    }

    /* ───────────────────────────── Unggahan CSV ───────────────────────────── */

    private function berkas(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'uji').'.csv';
        file_put_contents($path, $isi);

        return $path;
    }

    public function test_a_csv_of_account_balances_is_really_applied(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);
        $this->petakan('1-10002', 'PA08');

        $path = $this->berkas("kode,nilai,saldo_awal\n4-10001,-812000000,\n1-10002,95000000,70000000\n");
        $hasil = app(CsvIntake::class)->apply($path, '2026-08');
        @unlink($path);

        $this->assertTrue($hasil->ok());
        $this->assertSame(812_000_000.0, (float) AccountBalance::where('code', 'PA01')->value('amount'));
        $this->assertSame(70_000_000.0, (float) AccountBalance::where('code', 'PA08')->value('opening'));
    }

    public function test_a_csv_from_indonesian_excel_is_understood(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);

        // Titik koma sebagai pemisah, angka bergaya Indonesia, dan BOM di depan.
        $path = $this->berkas("\u{FEFF}Kode;Nilai\n4-10001;-812.000.000,50\n");
        $hasil = app(CsvIntake::class)->apply($path, '2026-08');
        @unlink($path);

        $this->assertTrue($hasil->ok());
        $this->assertSame(812_000_000.5, (float) AccountBalance::where('code', 'PA01')->value('amount'));
    }

    public function test_a_csv_of_kpi_realisations_is_applied_to_the_objectives(): void
    {
        DB::table('department_objectives')->insert([
            'entity_id' => $this->entitas, 'period' => '2026-08', 'dept_code' => 'QC', 'kpi_code' => 'KPI-01',
            'kpi_name' => 'Uji mutu', 'polarity' => 'Naik', 'target' => 100, 'actual' => 0,
            'achievement_pct' => 0, 'status' => 'Di Bawah Target', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $path = $this->berkas("periode,dept_code,kpi_code,target,actual\n2026-08,QC,KPI-01,100,90\n");
        $hasil = app(CsvIntake::class)->apply($path, '2026-08');
        @unlink($path);

        $this->assertTrue($hasil->ok());
        $this->assertSame(90.0, (float) DB::table('department_objectives')->where('kpi_code', 'KPI-01')->value('actual'));
    }

    public function test_a_csv_with_unknown_columns_is_refused_with_a_useful_message(): void
    {
        $path = $this->berkas("entah,apa\n1,2\n");
        $hasil = app(CsvIntake::class)->apply($path, '2026-08');
        @unlink($path);

        $this->assertSame(IntakeResult::DITOLAK, $hasil->outcome);
        $this->assertStringContainsString('Judul kolom tidak dikenali', $hasil->message);
    }

    /* ─────────────────────────── Tarikan dari Odoo ─────────────────────────── */

    private function sambungan(): OdooConnection
    {
        return OdooConnection::create([
            'base_url' => 'https://erp.uji.test',
            'database_name' => 'erp_uji',
            'username' => 'integrasi@uji.test',
            'api_key' => 'kunci-odoo',
            'is_active' => true,
            'fills_revenue' => true,
        ]);
    }

    /**
     * Odoo palsu: menjawab masuk, daftar perusahaan, bagan akun, lalu saldo per
     * rentang tanggal. Kuncinya di sini adalah MEMBEDAKAN tiga rentang yang
     * diminta penarik.
     *
     * Nama tampilan akun sengaja dibuat TANPA kode di depan (seperti Odoo baru),
     * supaya terbukti kodenya diambil dari id lewat bagan akun — bukan dipotong
     * dari nama tampilan.
     *
     * @param  array<string, array<string, float>>  $perRentang  "sejak|sampai" => kode akun => saldo
     * @param  array<int, array{id: int, name: string}>  $perusahaan
     */
    private function odooPalsu(array $perRentang, array $perusahaan = [['id' => 1, 'name' => 'PT Uji']]): void
    {
        // id akun dibuat tetap supaya jawaban read_group dan bagan akun sepakat.
        $idAkun = [];
        foreach ($perRentang as $saldo) {
            foreach (array_keys($saldo) as $kode) {
                $idAkun[$kode] ??= count($idAkun) + 101;
            }
        }

        Http::fake(['erp.uji.test/jsonrpc' => function ($request) use ($perRentang, $perusahaan, $idAkun) {
            $params = $request->data()['params'];

            if (in_array($params['method'], ['authenticate', 'login'], true)) {
                return Http::response(['jsonrpc' => '2.0', 'result' => 7]);
            }

            $model = $params['args'][3];

            if ($model === 'res.company') {
                return Http::response(['jsonrpc' => '2.0', 'result' => collect($perusahaan)
                    ->map(fn ($c) => ['id' => $c['id'], 'name' => $c['name']])->all()]);
            }

            if ($model === 'account.account') {
                return Http::response(['jsonrpc' => '2.0', 'result' => collect($idAkun)
                    ->map(fn ($id, $kode) => ['id' => $id, 'code' => $kode, 'name' => 'Nama Akun'])
                    ->values()->all()]);
            }

            $domain = $params['args'][5][0];
            $sejak = $domain[1][2];
            $sampai = $domain[2][2];
            $saldo = $perRentang[$sejak.'|'.$sampai] ?? [];

            return Http::response(['jsonrpc' => '2.0', 'result' => collect($saldo)
                ->map(fn ($nilai, $kode) => ['account_id' => [$idAkun[$kode], 'Nama Akun'], 'balance' => $nilai])
                ->values()->all()]);
        }]);
    }

    public function test_flow_posts_take_the_year_to_date_window_and_balance_posts_the_closing_balance(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);   // aliran
        $this->petakan('1-10002', 'PA08');                // neraca

        $this->odooPalsu([
            // YTD: 1 Jan – 31 Agu → dipakai pos aliran
            '2026-01-01|2026-08-31' => ['4-10001' => -812_000_000, '1-10002' => 95_000_000],
            // sejak awal buku – 31 Agu → saldo akhir pos neraca
            '1900-01-01|2026-08-31' => ['4-10001' => -3_000_000_000, '1-10002' => 120_000_000],
            // sejak awal buku – 31 Des tahun lalu → saldo awal tahun
            '1900-01-01|2025-12-31' => ['1-10002' => 70_000_000],
            // mutasi bulan berjalan → realisasi revenue
            '2026-08-01|2026-08-31' => ['4-10001' => -150_000_000],
        ]);

        $hasil = app(OdooPuller::class)->pull($this->sambungan(), '2026-08');

        $this->assertTrue($hasil->ok(), $hasil->message);
        // Pos aliran memakai YTD, bukan saldo akumulatif sejak awal buku.
        $this->assertSame(812_000_000.0, (float) AccountBalance::where('code', 'PA01')->value('amount'));
        // Pos neraca memakai saldo akhir + saldo awal tahun.
        $this->assertSame(120_000_000.0, (float) AccountBalance::where('code', 'PA08')->value('amount'));
        $this->assertSame(70_000_000.0, (float) AccountBalance::where('code', 'PA08')->value('opening'));
        // Realisasi revenue = mutasi bulan itu saja, bukan YTD.
        $this->assertSame(150_000_000.0, (float) RevenueTarget::where('period', '2026-08')->value('actual'));
    }

    public function test_the_account_code_comes_from_the_chart_not_from_the_display_name(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);

        // Nama tampilan akun di Odoo palsu ini adalah "Nama Akun" saja — tanpa
        // kode di depannya, seperti Odoo baru. Kalau kodenya dipotong dari nama
        // tampilan, tidak ada satu pun pos yang dikenali.
        $this->odooPalsu(['2026-01-01|2026-08-31' => ['4-10001' => -812_000_000]]);

        $hasil = app(OdooPuller::class)->pull($this->sambungan(), '2026-08');

        $this->assertTrue($hasil->ok(), $hasil->message);
        $this->assertSame(812_000_000.0, (float) AccountBalance::where('code', 'PA01')->value('amount'));
    }

    public function test_a_database_with_several_companies_is_refused_until_one_is_chosen(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);
        $this->odooPalsu(['2026-01-01|2026-08-31' => ['4-10001' => -812_000_000]], [
            ['id' => 1, 'name' => 'PT Erdigma'],
            ['id' => 2, 'name' => 'PT Herbatech'],
        ]);

        // Menjumlahkan beberapa perusahaan menjadi satu tidak menimbulkan galat —
        // angkanya sekadar menjadi terlalu besar. Karena itu ditolak di muka,
        // termasuk pada tarikan terjadwal yang tidak ada orang menungguinya.
        $this->expectExceptionMessage('memuat 2 perusahaan');
        app(OdooPuller::class)->pull($this->sambungan(), '2026-08');
    }

    public function test_choosing_a_company_lets_the_pull_through(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);
        $this->odooPalsu(['2026-01-01|2026-08-31' => ['4-10001' => -812_000_000]], [
            ['id' => 1, 'name' => 'PT Erdigma'],
            ['id' => 2, 'name' => 'PT Herbatech'],
        ]);

        $sambungan = $this->sambungan();
        $sambungan->update(['company_id' => 1, 'company_name' => 'PT Erdigma']);

        $this->assertTrue(app(OdooPuller::class)->pull($sambungan, '2026-08')->ok());

        // Perusahaan yang dipilih ikut dikirim sebagai penyaring, bukan hanya disimpan.
        Http::assertSent(fn ($r) => $r->data()['params']['args'][3] !== 'account.move.line'
            || collect($r->data()['params']['args'][5][0])->contains(fn ($d) => $d[0] === 'company_id' && $d[2] === 1));
    }

    public function test_revenue_is_left_alone_when_the_connection_says_so(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);
        $this->odooPalsu(['2026-01-01|2026-08-31' => ['4-10001' => -812_000_000]]);

        $sambungan = $this->sambungan();
        $sambungan->update(['fills_revenue' => false]);

        app(OdooPuller::class)->pull($sambungan, '2026-08');

        $this->assertSame(0, RevenueTarget::where('period', '2026-08')->count());
    }

    public function test_an_odoo_error_is_reported_instead_of_being_taken_as_data(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);

        // Odoo mengirim galat sebagai HTTP 200 berisi {"error": …}.
        Http::fake(['erp.uji.test/jsonrpc' => Http::response([
            'jsonrpc' => '2.0',
            'error' => ['message' => 'Odoo Server Error', 'data' => ['message' => 'Access Denied']],
        ], 200)]);

        $this->expectExceptionMessage('Access Denied');
        app(OdooPuller::class)->pull($this->sambungan(), '2026-08');
    }

    public function test_the_odoo_api_key_is_stored_encrypted(): void
    {
        $this->sambungan();

        $mentah = (string) DB::table('odoo_connections')->value('api_key');
        $this->assertNotSame('kunci-odoo', $mentah);
        $this->assertStringNotContainsString('kunci-odoo', $mentah);
        $this->assertSame('kunci-odoo', OdooConnection::first()->api_key);
    }

    /* ──────────── Halaman Integrasi & Gateway: yang tampil harus nyata ──────────── */

    private function masukSebagaiAdmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::where('email', 'superadmin@emc.co.id')->firstOrFail());
        app(EntityContext::class)->use($this->entitas);
    }

    public function test_the_integration_chain_shows_the_real_state_not_a_fixed_badge(): void
    {
        $this->masukSebagaiAdmin();

        // Belum ada apa pun: ketiga mata rantai harus mengaku belum siap.
        $rantai = Livewire::test(SystemIntegration::class)->viewData('rantai');

        $this->assertSame('belum diatur', $rantai['odoo']['label']);
        $this->assertSame('belum ada data', $rantai['finance']['label']);
        $this->assertSame('belum ada kunci', $rantai['bsc']['label']);

        // Pos akun terisi tetapi rasio belum terhitung pun harus terbaca apa
        // adanya — bukan langsung dinyatakan beres.
        $this->petakan('4-10001', 'PA01', balik: true);
        $this->sambungan()->forceFill(['last_status' => 'ok', 'last_run_at' => now()])->save();
        app(AccountIntake::class)->apply('2026-08', [['code' => '4-10001', 'amount' => -812_000_000]],
            ['idempotency_key' => 'IDEMP-RANTAI']);

        $rantai = Livewire::test(SystemIntegration::class)->viewData('rantai');
        $this->assertSame('tersambung', $rantai['odoo']['label']);
        $this->assertSame('belum terhitung', $rantai['finance']['label']);

        // Baru setelah cukup pos terisi sehingga rasio benar-benar keluar.
        $this->petakan('5-10001', 'PA02');
        app(AccountIntake::class)->apply('2026-08', [
            ['code' => '4-10001', 'amount' => -812_000_000],
            ['code' => '5-10001', 'amount' => 430_000_000],
        ], ['idempotency_key' => 'IDEMP-RANTAI-2']);

        $rantai = Livewire::test(SystemIntegration::class)->viewData('rantai');

        $this->assertSame('tersambung', $rantai['odoo']['label']);
        $this->assertSame('terhitung', $rantai['finance']['label']);
        $this->assertStringContainsString('pos akun terisi', $rantai['finance']['detail']);
    }

    public function test_the_summary_boxes_read_the_stored_posts_not_the_form(): void
    {
        $this->masukSebagaiAdmin();

        // Kosong = katakan kosong, jangan menampilkan angka contoh dari kode.
        $halaman = Livewire::test(SystemIntegration::class);
        $this->assertNull($halaman->viewData('saldoBerjalan')['salesPayload']);
        $halaman->assertSee('belum diisi');

        $this->petakan('4-10001', 'PA01', balik: true);
        app(AccountIntake::class)->apply('2026-08', [['code' => '4-10001', 'amount' => -812_000_000]],
            ['idempotency_key' => 'IDEMP-KOTAK']);

        // Satuan layar adalah juta rupiah.
        $halaman = Livewire::test(SystemIntegration::class);
        $this->assertSame(812.0, $halaman->viewData('saldoBerjalan')['salesPayload']);
        $this->assertSame(812.0, $halaman->get('salesPayload'));
    }

    public function test_an_empty_field_leaves_the_post_untouched_instead_of_writing_zero(): void
    {
        $this->masukSebagaiAdmin();
        $this->petakan('4-10001', 'PA01', balik: true);
        app(AccountIntake::class)->apply('2026-08', [['code' => '4-10001', 'amount' => -812_000_000]],
            ['idempotency_key' => 'IDEMP-KOSONG']);

        // Hanya HPP yang diisi; pos lain dibiarkan kosong. Nol itu DATA — rasio
        // menghitungnya sebagai nilai sungguhan — jadi kolom kosong tidak boleh
        // diam-diam menjadi nol.
        Livewire::test(SystemIntegration::class)
            ->set('financePeriod', '2026-08')
            ->set('hppPayload', 430)
            ->set('kasPayload', null)
            ->set('piutangPayload', null)
            ->set('persediaanPayload', null)
            ->set('hutangPayload', null)
            ->set('modalPayload', null)
            ->set('opexPayload', null)
            ->call('processFinancePayload');

        $this->assertSame(430_000_000.0, (float) AccountBalance::where('code', 'PA02')->value('amount'));
        // Penjualan hasil tarikan Odoo tetap utuh, pos kosong tidak dibuat.
        $this->assertSame(812_000_000.0, (float) AccountBalance::where('code', 'PA01')->value('amount'));
        $this->assertNull(AccountBalance::where('code', 'PA08')->first());
    }

    public function test_the_odoo_side_guide_is_available_from_the_page_itself(): void
    {
        $this->masukSebagaiAdmin();

        // Panduan langkah di Odoo dibaca dari aplikasi, bukan dari berkas dokumen
        // terpisah — orang yang sedang mengisi sambungan ada di layar ini.
        $halaman = Livewire::test(OdooIntegration::class)
            ->assertDontSee('Account Security')
            ->call('bukaPanduan');

        $halaman->assertSee('Yang perlu disiapkan di Odoo')
            ->assertSee('Internal User')
            ->assertSee('New API Key')
            ->assertSee('Posted')
            ->assertSee('beberapa perusahaan');

        $halaman->call('tutupPanduan')->assertDontSee('Account Security');
    }

    public function test_the_page_no_longer_advertises_an_endpoint_that_does_not_exist(): void
    {
        $this->masukSebagaiAdmin();

        Livewire::test(SystemIntegration::class)
            // Rute ini tidak pernah ada, dan kuncinya dulu berupa teks tetap.
            ->assertDontSee('bsc/sync/finance-coa')
            ->assertDontSee('bsc_live_secret_key_2026_hop4')
            ->assertDontSee('X-BSC-API-KEY')
            // Yang ditampilkan sekarang adalah alamat yang benar-benar dilayani.
            ->assertSee('api/v1/consolidation')
            ->assertSee('X-API-KEY');
    }

    public function test_the_scheduled_command_runs_inside_the_owning_entity(): void
    {
        $this->petakan('4-10001', 'PA01', balik: true);
        $this->sambungan();
        $this->odooPalsu(['2026-01-01|2026-08-31' => ['4-10001' => -812_000_000]]);

        // Pada konsol tidak ada pengguna yang login, jadi pembatas entitas tidak
        // menyala sendiri: perintahnya wajib memasang konteks entitas pemilik,
        // kalau tidak barisnya tertulis tanpa tuan.
        app(EntityContext::class)->forget();
        $this->artisan('bsc:tarik-odoo', ['--period' => '2026-08'])->assertSuccessful();

        $baris = DB::table('account_balances')->where('code', 'PA01')->first();
        $this->assertNotNull($baris);
        $this->assertSame($this->entitas, (int) $baris->entity_id);
    }
}
