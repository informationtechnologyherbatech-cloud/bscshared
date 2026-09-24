<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\AccountBalance;
use App\Models\AccountMapping;
use App\Models\ApiKey;
use App\Models\DepartmentObjective;
use App\Models\FinancialRatio;
use App\Models\OdooConnection;
use App\Models\Period;
use App\Models\StagingLog;
use App\Models\WorkUnit;
use App\Support\Bsc\Integration\CsvIntake;
use App\Support\Bsc\MonitoringSync;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class SystemIntegration extends Component
{
    use AuthorizesWrites;
    use WithFileUploads;

    /**
     * Pemetaan akun CoA payload → pos akun (satuan payload: juta rupiah).
     * Aliran = nilai YTD; neraca = saldo akhir periode.
     */
    private const COA_KE_POS = [
        'salesPayload' => 'PA01',      // 4101 Penjualan
        'hppPayload' => 'PA02',        // 5101 HPP
        'opexPayload' => 'PA03',       // 6101 Beban operasional
        'persediaanPayload' => 'PA05', // 1301 Persediaan
        'piutangPayload' => 'PA06',    // 1201 Piutang usaha
        'hutangPayload' => 'PA07',     // 2101 Hutang usaha
        'kasPayload' => 'PA08',        // 1101 Kas & bank
        'modalPayload' => 'PA13',      // 3101 Modal / ekuitas
    ];

    public $csvFile;

    // Department Objective Inbound Payload
    public $deptPayload = '';
    public $kpiCodePayload = '';
    public $targetPayload = 0;
    public $actualPayload = 0;
    public $evidenceUrlPayload = '';

    // Finance ERP Inbound Payload (Wadah Penerimaan Finance) — satuan juta rupiah.
    // Nilai awalnya diisi dari pos akun periode berjalan pada mount(), bukan
    // dipatok di sini: angka contoh yang dipatok membuat layar tampak berisi
    // padahal entitasnya belum punya data sama sekali.
    public $financePeriod = '';
    public $salesPayload = 0;      // 4101 - Penjualan Produk
    public $hppPayload = 0;        // 5101 - HPP
    public $opexPayload = 0;       // 6101 - Beban Operasional
    public $kasPayload = 0;        // 1101 - Kas & Bank
    public $piutangPayload = 0;    // 1201 - Piutang
    public $persediaanPayload = 0; // 1301 - Persediaan
    public $hutangPayload = 0;     // 2101 - Hutang Usaha
    public $modalPayload = 0;      // 3101 - Modal / Ekuitas

    public function mount()
    {
        // Periode, departemen, dan KPI bawaan dari data entitas aktif — sebelumnya
        // dipatok 2026-08, PROD, dan KPI-PROD-001 milik Herbatech.
        $this->financePeriod = Period::currentPeriod();
        $this->isiDariPosAkun();
        $this->deptPayload = (string) (DepartmentObjective::where('period', $this->financePeriod)->orderBy('dept_code')->value('dept_code')
            ?? WorkUnit::active()->value('code') ?? '');
        $this->updatedDeptPayload();
    }

    /** KPI pertama departemen itu pada periode berjalan. */
    public function updatedDeptPayload()
    {
        $obj = DepartmentObjective::where('period', Period::currentPeriod())
            ->where('dept_code', $this->deptPayload)->orderBy('kpi_code')->first();

        $this->kpiCodePayload = (string) ($obj?->kpi_code ?? '');
        $this->targetPayload = $obj ? (float) $obj->target : 0;
        $this->actualPayload = $obj ? (float) $obj->actual : 0;
    }

    public function updatedKpiCodePayload()
    {
        $obj = DepartmentObjective::where('period', Period::currentPeriod())->where('kpi_code', $this->kpiCodePayload)->first();

        $this->targetPayload = $obj ? (float) $obj->target : 0;
        $this->actualPayload = $obj ? (float) $obj->actual : 0;
    }

    /** Isian formulir mengikuti pos akun yang tersimpan untuk periode itu. */
    private function isiDariPosAkun(): void
    {
        foreach ($this->saldoBerjalan($this->financePeriod) as $kolom => $nilai) {
            $this->{$kolom} = $nilai ?? 0;
        }
    }

    /** Ganti periode = ganti pula angka yang sedang dilihat & disunting. */
    public function updatedFinancePeriod(): void
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $this->financePeriod)) {
            $this->isiDariPosAkun();
        }
    }

    public function processManualPayload()
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $periode = Period::currentPeriod();
        $this->validate([
            'deptPayload' => ['required', Rule::in(WorkUnit::pluck('code')->all())],
            'kpiCodePayload' => ['required', 'string', 'max:50'],
            'targetPayload' => ['required', 'numeric'],
            'actualPayload' => ['required', 'numeric'],
            'evidenceUrlPayload' => ['nullable', 'url:http,https', 'max:500'],
        ], [], ['deptPayload' => 'departemen', 'kpiCodePayload' => 'kode KPI', 'actualPayload' => 'realisasi']);

        $obj = DepartmentObjective::where('period', $periode)
            ->where('dept_code', $this->deptPayload)
            ->where('kpi_code', $this->kpiCodePayload)
            ->first();

        if (! $obj) {
            $this->addError('kpiCodePayload', 'KPI ' . $this->kpiCodePayload . ' tidak ada di departemen ' . $this->deptPayload . ' pada periode ' . $periode . '.');

            return;
        }

        if (Period::where('period', $periode)->first()?->isClosed()) {
            session()->flash('error', 'Periode ' . $periode . ' telah DITUTUP (CLOSED). Payload ditolak.');

            return;
        }

        // Target KPI yang tertaut cascade ditetapkan di Cascade KPI, bukan oleh payload.
        // Capaian mengikuti polaritas (Naik/Turun/Rentang), sama dengan Objective Departemen.
        $target = $obj->kpi_cascade_id ? (float) $obj->target : (float) $this->targetPayload;
        $ach = RatioLibrary::objectiveAchievement((float) $this->actualPayload, $target, $obj->polarity);

        $obj->update([
            'actual' => $this->actualPayload,
            'target' => $target,
            'achievement_pct' => $ach,
            'status' => MonitoringSync::status($ach),
        ]);

        $idempotencyKey = 'IDEMP-' . strtoupper($this->deptPayload) . '-' . date('Ymd-His');

        StagingLog::create([
            'period' => $periode,
            'dept_code' => strtoupper($this->deptPayload),
            'idempotency_key' => $idempotencyKey,
            'status' => 'SCORED',
            'source_version' => 1,
            'message' => 'Integrasi API Manual Payload dari ' . $this->deptPayload . ' untuk ' . $obj->kpi_code . ' berhasil diproses & diskor.',
        ]);

        session()->flash('message', 'Payload integrasi dari ' . $this->deptPayload . ' berhasil diproses! (Idempotency Key: ' . $idempotencyKey . ')');
    }

    /**
     * Saldo CoA dari ERP masuk ke Pos Akun, lalu 19 rasio dihitung ulang oleh
     * mesin yang sama dengan menu Pos Akun. Sebelumnya rasio dihitung sendiri di
     * sini dengan target tetap dan ditulis langsung ke Rasio Keuangan, sehingga
     * angkanya bisa berbeda dengan piramida.
     */
    public function processFinancePayload()
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }
        // Menulis pos akun & menghitung ulang rasio: wajib juga berhak atas rasio
        // (sama dengan menu Pos Akun) — Admin HRIS tidak boleh mengubah data keuangan.
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        $aturan = ['financePeriod' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']];
        foreach (array_keys(self::COA_KE_POS) as $kolom) {
            $aturan[$kolom] = ['required', 'numeric'];
        }
        $this->validate($aturan, ['financePeriod.regex' => 'Periode harus YYYY-MM.'], ['financePeriod' => 'periode']);

        if (Period::where('period', $this->financePeriod)->first()?->isClosed()) {
            session()->flash('error', 'Periode ' . $this->financePeriod . ' telah DITUTUP (CLOSED). Data Finance ditolak.');

            return;
        }

        DB::transaction(function () {
            foreach (self::COA_KE_POS as $kolom => $pos) {
                AccountBalance::updateOrCreate(
                    ['period' => $this->financePeriod, 'code' => $pos],
                    ['amount' => (float) $this->{$kolom} * 1_000_000]
                );
            }

            app(RatioEngine::class)->materialize($this->financePeriod);
        });

        $netProfit = (float) $this->salesPayload - (float) $this->hppPayload - (float) $this->opexPayload;
        $idempotencyKey = 'IDEMP-FIN-COA-' . date('Ymd-His');

        StagingLog::create([
            'period' => $this->financePeriod,
            'dept_code' => 'FIN',
            'idempotency_key' => $idempotencyKey,
            'status' => 'SCORED',
            'source_version' => 1,
            'message' => 'Penerimaan Data Finance ERP ke Pos Akun (Penjualan: ' . rupiah($this->salesPayload) . ' JT, HPP: ' . rupiah($this->hppPayload) . ' JT, Laba: ' . rupiah($netProfit) . ' JT); rasio keuangan dihitung ulang.',
        ]);

        session()->flash('message', 'Data CoA Finance ' . $this->financePeriod . ' masuk ke Pos Akun dan rasio keuangan dihitung ulang. '
            . 'Pos akun lain (beban tenaga kerja, aset & liabilitas lancar, total aset/liabilitas, modal disetor, data HRIS) dilengkapi di menu Pos Akun.');
    }

    /**
     * Berkas yang diunggah BENAR-BENAR dibaca dan diterapkan.
     *
     * Dua bentuk berkas dilayani, dikenali dari judul kolomnya: saldo akun
     * (masuk ke Pos Akun lalu rasio dihitung ulang) dan realisasi KPI. Aturan
     * yang berlaku sama persis dengan jalur API — keduanya memakai kelas
     * pemasukan yang sama, sehingga periode tertutup, pemetaan akun, dan
     * penanda idempotensi diperlakukan seragam.
     */
    public function uploadCsv(CsvIntake $csv)
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:2048',
        ], [
            'csvFile.mimes' => 'Berkasnya harus CSV. Dari Excel: Simpan Sebagai → CSV.',
        ]);

        try {
            $hasil = $csv->apply($this->csvFile->getRealPath(), Period::currentPeriod());
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Berkas '.$this->csvFile->getClientOriginalName().' tidak dapat diproses: '.$e->getMessage());

            return;
        }

        $nama = $this->csvFile->getClientOriginalName();
        $this->reset('csvFile');

        if (! $hasil->ok()) {
            session()->flash('error', $nama.' — '.$hasil->message);

            return;
        }

        session()->flash('message', $nama.' diproses: '.$hasil->message
            .($hasil->problems !== [] ? ' Baris yang dilewati: '.implode('; ', array_slice($hasil->problems, 0, 5)).'.' : ''));
    }

    /**
     * Keadaan NYATA tiap mata rantai integrasi.
     *
     * Sebelumnya ketiga kotak di banner selalu bertuliskan "Connected" — kata
     * yang ditulis di berkas tampilan, bukan disimpulkan dari apa pun. Layar
     * pemantauan yang selalu hijau tidak memantau apa-apa.
     *
     * @return array<string, array{status: string, label: string, detail: string}>
     */
    private function rantaiIntegrasi(string $periode): array
    {
        $odoo = OdooConnection::first();

        $hop1 = match (true) {
            ! $odoo => ['mati', 'belum diatur', 'Sambungan Odoo belum dibuat.'],
            ! $odoo->is_active => ['diam', 'nonaktif', 'Tarikan terjadwal dimatikan.'],
            $odoo->last_status === 'galat' => ['galat', 'galat', (string) $odoo->last_message],
            $odoo->last_status === 'ok' => ['hidup', 'tersambung',
                'Terakhir '.$odoo->last_run_at?->diffForHumans().' · '.AccountMapping::count().' akun dipetakan.'],
            default => ['diam', 'belum pernah ditarik', AccountMapping::count().' akun dipetakan; tekan Tarik sekarang.'],
        };

        // "Finance Monitoring" di aplikasi ini = pos akun yang terisi lalu
        // dihitung menjadi 19 rasio. Itulah yang benar-benar dapat diperiksa.
        $terisi = AccountBalance::where('period', $periode)->whereNotNull('amount')->count();
        $rasio = FinancialRatio::where('period', $periode)->count();
        $hop2 = match (true) {
            $terisi === 0 => ['mati', 'belum ada data', 'Pos akun periode '.$periode.' masih kosong.'],
            $rasio === 0 => ['galat', 'belum terhitung', $terisi.' pos akun terisi, tetapi rasio belum dihitung.'],
            default => ['hidup', 'terhitung', $terisi.' dari 16 pos akun terisi · '.$rasio.' rasio dihitung.'],
        };

        // Hop 4 = aplikasi ini sebagai sumber bacaan holding. Yang menentukan
        // hidup-matinya adalah ada tidaknya kunci API aktif, bukan kata-kata.
        $kunci = ApiKey::where('is_active', true)->get();
        $terpakai = $kunci->max('last_used_at');
        $hop4 = match (true) {
            $kunci->isEmpty() => ['mati', 'belum ada kunci', 'Terbitkan kunci API di Setting Sistem → tab API.'],
            $terpakai === null => ['diam', 'siap', $kunci->count().' kunci aktif; holding belum pernah membaca.'],
            default => ['hidup', 'dibaca holding', 'Terakhir dibaca '.$terpakai->diffForHumans().'.'],
        };

        return [
            'odoo' => ['status' => $hop1[0], 'label' => $hop1[1], 'detail' => $hop1[2]],
            'finance' => ['status' => $hop2[0], 'label' => $hop2[1], 'detail' => $hop2[2]],
            'bsc' => ['status' => $hop4[0], 'label' => $hop4[1], 'detail' => $hop4[2]],
        ];
    }

    /**
     * Saldo pos akun periode ini dalam JUTA — satuan yang dipakai formulir.
     * Null = pos itu memang belum diisi, jangan ditampilkan sebagai angka.
     *
     * @return array<string, float|null>
     */
    private function saldoBerjalan(string $periode): array
    {
        $saldo = AccountBalance::where('period', $periode)->pluck('amount', 'code');

        return collect(self::COA_KE_POS)->mapWithKeys(fn ($pos, $kolom) => [
            $kolom => isset($saldo[$pos]) ? (float) $saldo[$pos] / 1_000_000 : null,
        ])->all();
    }

    public function render()
    {
        $recentLogs = StagingLog::latest()->take(8)->get();
        $financeLogs = StagingLog::where('dept_code', 'FIN')->latest()->take(5)->get();
        $financialRatios = FinancialRatio::where('period', $this->financePeriod)->orderBy('ratio_code')->get();

        $periode = Period::currentPeriod();

        // Kotak ringkasan membaca POS AKUN yang benar-benar tersimpan, bukan isi
        // formulir. Sebelumnya keduanya satu nilai, sehingga entitas yang pos
        // akunnya masih kosong tetap menampilkan angka contoh dari kode program.
        $saldo = $this->saldoBerjalan($this->financePeriod);
        $labaBersih = $saldo['salesPayload'] === null ? null
            : $saldo['salesPayload'] - (float) $saldo['hppPayload'] - (float) $saldo['opexPayload'];

        return view('livewire.system-integration', [
            'recentLogs' => $recentLogs,
            'financeLogs' => $financeLogs,
            'financialRatios' => $financialRatios,
            'saldoBerjalan' => $saldo,
            'labaBersih' => $labaBersih,
            'rantai' => $this->rantaiIntegrasi($this->financePeriod),
            'units' => WorkUnit::active()->get(),
            'kpiOptions' => DepartmentObjective::where('period', $periode)->where('dept_code', $this->deptPayload)->orderBy('kpi_code')->get(),
            'currentPeriod' => $periode,
        ])->layout('layouts.app', ['title' => 'Integrasi Sistem & Gateway']);
    }
}
