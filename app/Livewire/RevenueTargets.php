<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\RevenuePlan;
use App\Models\RevenueTarget;
use App\Support\Bsc\RevenueForecast;
use App\Support\EntityContext;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tingkat 1 — target & realisasi revenue bulanan entitas aktif.
 *
 * Mengikuti sheet "L1 Target Revenue" bagian G: target tahunan difasing per
 * bulan, lalu pencapaian dihitung KUMULATIF (Jan s.d. bulan berjalan). Angka
 * kumulatif itulah F1 pada rumus skor puncak: 0,45 × F1 + 0,55 × F2.
 */
class RevenueTargets extends Component
{
    use AuthorizesWrites;

    private const BULAN = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];

    #[Url]
    public string $year = '';

    /** @var array<string, array{target: string, actual: string}> */
    public array $rows = [];

    /** Target setahun untuk dibantu difasing ke 12 bulan. */
    public string $annualTarget = '';

    /** Target setahun yang disahkan direksi (sheet Asumsi B7). */
    public string $approvedTarget = '';

    /** Target revisi di tengah tahun (Asumsi B8) — sumber faktor revisi KPI. */
    public string $revisedTarget = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = now()->format('Y');
        }

        $this->loadYear();
    }

    public function updatedYear(): void
    {
        if (! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = now()->format('Y');
        }

        $this->loadYear();
    }

    private function loadYear(): void
    {
        $tersimpan = RevenueTarget::where('period', 'like', $this->year.'-%')->get()->keyBy('period');

        $this->rows = [];
        foreach (array_keys(self::BULAN) as $bulan) {
            $baris = $tersimpan->get($this->year.'-'.$bulan);
            $this->rows[$bulan] = [
                'target' => $baris ? $this->angka($baris->target) : '',
                'actual' => $baris && $baris->actual !== null ? $this->angka($baris->actual) : '',
            ];
        }

        // Angka setahun untuk fasing diambil dari target disahkan/revisi (annualTargetValue).
        $this->annualTarget = '';

        $rencana = RevenuePlan::where('year', $this->year)->first();
        $this->approvedTarget = $rencana ? $this->angka($rencana->approved_target) : '';
        $this->revisedTarget = $rencana && $rencana->revised_target !== null ? $this->angka($rencana->revised_target) : '';
        $this->resetErrorBag();
    }

    private function angka(mixed $nilai): string
    {
        $n = (float) $nilai;

        return floor($n) == $n ? number_format($n, 0, '.', '') : (string) $n;
    }

    /** Bagi target setahun rata ke 12 bulan. */
    public function phaseEvenly(): void
    {
        $total = $this->annualTargetValue();

        if ($total === null) {
            return;
        }

        $perBulan = round($total / 12, 2);
        $sisa = round($total - $perBulan * 11, 2);

        foreach (array_keys(self::BULAN) as $i => $bulan) {
            $this->rows[$bulan]['target'] = $this->angka($i === 11 ? $sisa : $perBulan);
        }
    }

    /**
     * Fasing mengikuti pola musiman realisasi tahun sebelumnya, persis sheet L1
     * bagian G: indeks = realisasi bulan ÷ estimasi akhir tahun (run-rate);
     * bulan tanpa realisasi berbagi rata sisa indeks. Cukup realisasi sebagian
     * tahun.
     */
    public function phaseBySeason(): void
    {
        $total = $this->annualTargetValue();

        if ($total === null) {
            return;
        }

        $lalu = (string) ((int) $this->year - 1);
        $realisasi = RevenueTarget::where('period', 'like', $lalu.'-%')
            ->whereNotNull('actual')
            ->pluck('actual', 'period');

        $perBulan = [];
        foreach (array_keys(self::BULAN) as $bulan) {
            $nilai = $realisasi->get($lalu.'-'.$bulan);
            $perBulan[$bulan] = $nilai === null ? null : (float) $nilai;
        }

        $indeks = RevenueForecast::seasonalIndex($perBulan);

        if ($indeks === null) {
            $this->addError('annualTarget', 'Pola musiman butuh realisasi tahun '.$lalu.' (minimal satu bulan). Pakai "Bagi rata" atau isi dulu realisasinya.');

            return;
        }

        foreach (RevenueForecast::phase($total, $indeks) as $bulan => $nilai) {
            $this->rows[$bulan]['target'] = $this->angka($nilai);
        }
    }

    private function annualTargetValue(): ?float
    {
        $this->resetErrorBag('annualTarget');

        // Sumber angka setahun: revisi bila ada, lalu angka disahkan. $annualTarget
        // tetap diterima (isian lama/uji) — dan teks "Rp 1.000.000,5" tetap dipahami.
        $sumber = trim((string) $this->annualTarget) !== '' ? $this->annualTarget
            : (trim((string) $this->revisedTarget) !== '' ? $this->revisedTarget : $this->approvedTarget);
        $bersih = is_numeric($sumber)
            ? $sumber
            : str_replace(['Rp', '.', ',', ' '], ['', '', '.', ''], (string) $sumber);

        if (! is_numeric($bersih) || (float) $bersih <= 0) {
            $this->addError('annualTarget', 'Isi target setahun "Disahkan direksi" lebih dulu (angka lebih dari 0).');

            return null;
        }

        return (float) $bersih;
    }

    public function save(): void
    {
        if ($this->lacksPermission('manage revenue')) {
            return;
        }

        $this->validate([
            'rows.*.target' => ['nullable', 'numeric', 'min:0'],
            'rows.*.actual' => ['nullable', 'numeric', 'min:0'],
            'approvedTarget' => ['nullable', 'numeric', 'gt:0'],
            'revisedTarget' => ['nullable', 'numeric', 'gt:0'],
        ], [], [
            'rows.*.target' => 'target',
            'rows.*.actual' => 'realisasi',
            'approvedTarget' => 'target disahkan',
            'revisedTarget' => 'target revisi',
        ]);

        if ($this->revisedTarget !== '' && $this->approvedTarget === '') {
            $this->addError('approvedTarget', 'Isi target disahkan lebih dulu sebelum target revisi.');

            return;
        }

        if ($this->approvedTarget === '') {
            RevenuePlan::where('year', $this->year)->delete();
        } else {
            RevenuePlan::updateOrCreate(['year' => $this->year], [
                'approved_target' => (float) $this->approvedTarget,
                'revised_target' => $this->revisedTarget === '' ? null : (float) $this->revisedTarget,
            ]);
        }

        foreach ($this->rows as $bulan => $baris) {
            $periode = $this->year.'-'.$bulan;
            $target = $baris['target'] === '' ? null : (float) $baris['target'];
            $actual = $baris['actual'] === '' ? null : (float) $baris['actual'];

            if ($target === null && $actual === null) {
                RevenueTarget::where('period', $periode)->delete();

                continue;
            }

            RevenueTarget::updateOrCreate(
                ['period' => $periode],
                ['target' => $target ?? 0, 'actual' => $actual]
            );
        }

        $this->loadYear();
        session()->flash('message', 'Target & realisasi revenue '.$this->year.' tersimpan.');

        // F1 dihitung dari target & realisasi BULANAN; target setahun saja belum cukup.
        $adaTargetBulanan = collect($this->rows)->contains(fn ($r) => (float) ($r['target'] ?: 0) > 0);
        if ($this->approvedTarget !== '' && ! $adaTargetBulanan) {
            session()->flash('error', 'Target setahun tersimpan, tetapi target bulanan masih kosong sehingga Tingkat 1 (F1) belum terhitung. '
                .'Klik "Bagi rata 12 bulan" atau "Ikuti pola musiman", lalu Simpan lagi.');
        }
    }

    public function render()
    {
        // Hitung kumulatif dari isian yang sedang tampil, sehingga pengguna melihat
        // dampaknya sebelum menyimpan.
        $kumTarget = 0.0;
        $kumActual = 0.0;
        $ringkasan = [];

        foreach (self::BULAN as $bulan => $nama) {
            $t = (float) ($this->rows[$bulan]['target'] ?? 0 ?: 0);
            $a = $this->rows[$bulan]['actual'] ?? '';
            $kumTarget += $t;
            $kumActual += (float) ($a === '' ? 0 : $a);

            $ringkasan[$bulan] = [
                'nama' => $nama,
                'bulanan' => $t > 0 && $a !== '' ? min(100, (float) $a / $t * 100) : null,
                'kum_target' => $kumTarget,
                'kum_actual' => $kumActual,
                'kumulatif' => $kumTarget > 0 && $a !== '' ? min(100, $kumActual / $kumTarget * 100) : null,
            ];
        }

        return view('livewire.revenue-targets', [
            'ringkasan' => $ringkasan,
            'totalTarget' => $kumTarget,
            'totalActual' => $kumActual,
            'entity' => app(EntityContext::class)->entity(),
            'revisionFactor' => is_numeric($this->approvedTarget) && is_numeric($this->revisedTarget) && (float) $this->approvedTarget > 0
                ? (float) $this->revisedTarget / (float) $this->approvedTarget
                : null,
            'years' => range((int) now()->format('Y') - 3, (int) now()->format('Y') + 2),
        ])->layout('layouts.app', ['title' => 'Target Revenue']);
    }
}
