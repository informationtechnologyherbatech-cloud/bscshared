<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\RevenueTarget;
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

        $total = collect($this->rows)->sum(fn ($r) => (float) ($r['target'] ?: 0));
        $this->annualTarget = $total > 0 ? $this->angka($total) : '';
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
     * Fasing mengikuti pola musiman realisasi tahun sebelumnya, seperti indeks
     * musiman pada sheet L1 bagian G.
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

        $jumlah = (float) $realisasi->sum();

        if ($realisasi->count() < 12 || $jumlah <= 0) {
            $this->addError('annualTarget', 'Pola musiman butuh realisasi lengkap 12 bulan tahun '.$lalu.'. Pakai "Bagi rata" atau lengkapi dulu realisasinya.');

            return;
        }

        $terpakai = 0.0;
        foreach (array_keys(self::BULAN) as $i => $bulan) {
            if ($i === 11) {
                $nilai = round($total - $terpakai, 2);
            } else {
                $indeks = (float) $realisasi->get($lalu.'-'.$bulan, 0) / $jumlah;
                $nilai = round($total * $indeks, 2);
                $terpakai += $nilai;
            }
            $this->rows[$bulan]['target'] = $this->angka($nilai);
        }
    }

    private function annualTargetValue(): ?float
    {
        $this->resetErrorBag('annualTarget');
        $bersih = str_replace(['.', ',', ' '], ['', '.', ''], $this->annualTarget);

        if (! is_numeric($bersih) || (float) $bersih <= 0) {
            $this->addError('annualTarget', 'Isi target setahun lebih dulu (angka lebih dari 0).');

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
        ], [], [
            'rows.*.target' => 'target',
            'rows.*.actual' => 'realisasi',
        ]);

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
            'years' => range((int) now()->format('Y') - 3, (int) now()->format('Y') + 2),
        ])->layout('layouts.app', ['title' => 'Target Revenue']);
    }
}
