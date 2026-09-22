<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\Entity;
use App\Models\Period;
use App\Models\RevenueForecastPlan;
use App\Models\RevenuePlan;
use App\Models\RevenueTarget;
use App\Support\Bsc\RevenueForecast;
use App\Support\EntityContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Sheet "L1 Target Revenue" bagian A–G: direksi menyusun target revenue
 * setahun dari lima sudut pandang (run-rate, CAGR, regresi, bottom-up
 * brand × channel, inisiatif Ansoff + koreksi SWOT), mengesahkan satu angka,
 * lalu memfasingnya ke 12 bulan dengan indeks musiman tahun dasar.
 */
class RevenuePlanning extends Component
{
    use AuthorizesWrites;

    /** Channel bawaan Erdigma (workbook). Entitas lain mengisi channelnya sendiri. */
    private const CHANNEL_ERDIGMA = ['SOC', 'TTC', 'ECO', 'OFD', 'PTN'];

    #[Url]
    public string $year = '';

    /** @var array<string, string> tahun => realisasi (3 tahun sebelum tahun dasar) */
    public array $history = [];

    public string $baseYtd = '';

    public string $baseMonths = '';

    /** @var array<int, string> */
    public array $channels = [];

    /** @var array<int, array{name: string, cells: array<int, string>, growth: string}> growth dalam persen */
    public array $brands = [];

    /** @var array<int, array{quadrant: string, initiative: string, revenue: string, probability: string}> probabilitas dalam persen */
    public array $ansoff = [];

    /** @var array{s: string, w: string, o: string, t: string} */
    public array $swot = ['s' => '', 'w' => '', 'o' => '', 't' => ''];

    /** Koreksi SWOT dalam persen. */
    public string $swotAdjustment = '0';

    public string $notes = '';

    public string $manualApproval = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = (string) ((int) Period::activeYear() + 1); // tahun sesudah periode aktif
        }

        $this->load();
    }

    public function updatedYear(): void
    {
        if (! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = (string) ((int) now()->format('Y') + 1);
        }

        $this->load();
    }

    private function baseYear(): int
    {
        return (int) $this->year - 1;
    }

    private function load(): void
    {
        $rencana = RevenueForecastPlan::where('year', $this->year)->first();

        $this->history = [];
        foreach (range($this->baseYear() - 3, $this->baseYear() - 1) as $t) {
            $nilai = $rencana?->history[$t] ?? null;
            $this->history[(string) $t] = $nilai === null ? '' : $this->angka((float) $nilai);
        }

        $this->baseYtd = $rencana?->base_ytd === null ? '' : $this->angka($rencana->base_ytd);
        $this->baseMonths = $rencana?->base_months === null ? '' : (string) $rencana->base_months;

        $entitas = app(EntityContext::class)->entity();
        $this->channels = $rencana?->channels
            ?? ($entitas?->industry === Entity::DIGITAL_MARKETING ? self::CHANNEL_ERDIGMA : []);

        $this->brands = collect($rencana?->brands ?? [])->map(fn ($b) => [
            'name' => (string) ($b['name'] ?? ''),
            'cells' => array_map(fn ($v) => $v === null ? '' : $this->angka((float) $v), array_values($b['cells'] ?? [])),
            'growth' => isset($b['growth']) ? $this->angka((float) $b['growth'] * 100) : '',
        ])->all();

        $this->ansoff = collect($rencana?->ansoff ?? [])->map(fn ($a) => [
            'quadrant' => (string) ($a['quadrant'] ?? 'penetrasi'),
            'initiative' => (string) ($a['initiative'] ?? ''),
            'revenue' => isset($a['revenue']) ? $this->angka((float) $a['revenue']) : '',
            'probability' => isset($a['probability']) ? $this->angka((float) $a['probability'] * 100) : '',
        ])->all();

        $this->swot = array_merge(['s' => '', 'w' => '', 'o' => '', 't' => ''], $rencana?->swot ?? []);
        $this->swotAdjustment = $this->angka((float) ($rencana?->swot_adjustment ?? 0) * 100);
        $this->notes = (string) ($rencana?->notes ?? '');
        $this->manualApproval = '';

        foreach ($this->brands as $i => $b) {
            $this->brands[$i]['cells'] = $this->padCells($b['cells']);
        }

        $this->resetErrorBag();
    }

    private function angka(float $n): string
    {
        return floor($n) == $n ? number_format($n, 0, '.', '') : rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
    }

    /** @param  array<int, string>  $cells */
    private function padCells(array $cells): array
    {
        $hasil = [];
        foreach (array_keys($this->channels) as $i) {
            $hasil[$i] = (string) ($cells[$i] ?? '');
        }

        return $hasil;
    }

    /* ---------------------------------------------------- baris dinamis */

    public function addChannel(): void
    {
        $this->channels[] = '';
        foreach ($this->brands as $i => $b) {
            $this->brands[$i]['cells'] = $this->padCells($b['cells']);
        }
    }

    public function removeChannel(int $index): void
    {
        unset($this->channels[$index]);
        $this->channels = array_values($this->channels);

        foreach ($this->brands as $i => $b) {
            unset($b['cells'][$index]);
            $this->brands[$i]['cells'] = $this->padCells(array_values($b['cells']));
        }
    }

    public function addBrand(): void
    {
        $this->brands[] = ['name' => '', 'cells' => $this->padCells([]), 'growth' => ''];
    }

    public function removeBrand(int $index): void
    {
        unset($this->brands[$index]);
        $this->brands = array_values($this->brands);
    }

    public function addInitiative(): void
    {
        $this->ansoff[] = ['quadrant' => 'penetrasi', 'initiative' => '', 'revenue' => '', 'probability' => ''];
    }

    public function removeInitiative(int $index): void
    {
        unset($this->ansoff[$index]);
        $this->ansoff = array_values($this->ansoff);
    }

    /* ------------------------------------------------------------- hitung */

    private static function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    /** Realisasi bulanan tahun dasar dari menu Target Revenue. */
    private function baseMonthly(): array
    {
        $tersimpan = RevenueTarget::where('period', 'like', $this->baseYear().'-%')
            ->whereNotNull('actual')->pluck('actual', 'period');

        $hasil = [];
        foreach (range(1, 12) as $b) {
            $kunci = str_pad((string) $b, 2, '0', STR_PAD_LEFT);
            $nilai = $tersimpan->get($this->baseYear().'-'.$kunci);
            $hasil[$kunci] = $nilai === null ? null : (float) $nilai;
        }

        return $hasil;
    }

    /** @return array<string, mixed> */
    private function compute(): array
    {
        $bulanan = $this->baseMonthly();
        $ada = array_filter($bulanan, fn ($v) => $v !== null);

        // Bagian A: dari data bulanan, kecuali ditimpa manual.
        $ytdOtomatis = $ada === [] ? null : array_sum($ada);
        $nOtomatis = count($ada);
        $ytd = self::num($this->baseYtd) ?? $ytdOtomatis;
        $n = is_numeric($this->baseMonths) ? (int) $this->baseMonths : $nOtomatis;
        $estimasi = RevenueForecast::yearEndEstimate($ytd, $n);

        // Bagian B.
        $deret = [];
        foreach ($this->history as $t => $v) {
            if (is_numeric($v)) {
                $deret[(int) $t] = (float) $v;
            }
        }
        if ($estimasi !== null) {
            $deret[$this->baseYear()] = $estimasi;
        }
        ksort($deret);
        // CAGR memakai rentang yang tersedia (workbook: 3 tahun); regresi butuh ≥ 3 titik.
        $cagr = count($deret) >= 2 ? RevenueForecast::cagr($deret) : null;
        $proyeksiCagr = $cagr === null || $estimasi === null ? null : $estimasi * (1 + $cagr);
        $regresi = count($deret) >= 3 ? RevenueForecast::linearForecast($deret, (int) $this->year) : null;

        // Bagian C.
        $channels = array_values(array_map(fn ($c) => trim((string) $c) !== '' ? trim((string) $c) : 'Channel', $this->channels));
        $kunci = array_map(fn ($i) => (string) $i, array_keys($channels));
        $brand = array_map(fn ($b) => [
            'name' => $b['name'] ?: 'Brand',
            'cells' => array_combine($kunci, array_map(fn ($v) => self::num($v), $this->padCells($b['cells']))) ?: [],
            'growth' => (self::num($b['growth']) ?? 0) / 100,
        ], $this->brands);
        $bottomUp = RevenueForecast::bottomUp($kunci, $brand);
        $adaBottomUp = $bottomUp['base'] > 0;
        $basisSelaras = ! $adaBottomUp || $estimasi === null
            ? null
            : abs($bottomUp['base'] - $estimasi) <= $estimasi * RevenueForecast::TOLERANSI_BASIS;

        // Bagian D & E.
        $ev = RevenueForecast::ansoffExpectedValue(array_map(fn ($a) => [
            'revenue' => self::num($a['revenue']),
            'probability' => (self::num($a['probability']) ?? 0) / 100,
        ], $this->ansoff));
        $koreksi = (self::num($this->swotAdjustment) ?? 0) / 100;

        // Bagian F.
        $metode = RevenueForecast::reconciliation($estimasi, $proyeksiCagr, $regresi, $adaBottomUp ? $bottomUp['target'] : null, $ev, $koreksi);
        $disahkan = RevenuePlan::where('year', $this->year)->first();

        // Bagian G.
        $indeks = RevenueForecast::seasonalIndex($bulanan, $estimasi);

        return [
            'monthly' => $bulanan,
            'ytd' => $ytd,
            'months' => $n,
            'auto_ytd' => $ytdOtomatis,
            'auto_months' => $nOtomatis,
            'estimate' => $estimasi,
            'series' => $deret,
            'yoy' => RevenueForecast::yoy($deret),
            'cagr' => $cagr,
            'cagr_projection' => $proyeksiCagr,
            'regression' => $regresi,
            'bottom_up' => $bottomUp,
            'channel_labels' => $channels,
            'base_consistent' => $basisSelaras,
            'ansoff_ev' => $ev,
            'methods' => $metode,
            'approved' => $disahkan?->approved_target,
            'index' => $indeks,
            'phasing' => $disahkan?->approved_target && $indeks ? RevenueForecast::phase($disahkan->approved_target, $indeks) : null,
        ];
    }

    /* ------------------------------------------------------------ simpan */

    public function save(): void
    {
        if ($this->lacksPermission('manage revenue')) {
            return;
        }

        $this->validate([
            'history.*' => ['nullable', 'numeric', 'min:0'],
            'baseYtd' => ['nullable', 'numeric', 'min:0'],
            'baseMonths' => ['nullable', 'integer', 'between:1,12'],
            'channels.*' => ['nullable', 'string', 'max:50'],
            'brands.*.name' => ['nullable', 'string', 'max:100'],
            'brands.*.cells.*' => ['nullable', 'numeric', 'min:0'],
            'brands.*.growth' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
            'ansoff.*.quadrant' => ['required', 'in:'.implode(',', array_keys(RevenueForecast::ANSOFF))],
            'ansoff.*.initiative' => ['nullable', 'string', 'max:255'],
            'ansoff.*.revenue' => ['nullable', 'numeric', 'min:0'],
            'ansoff.*.probability' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'swot.*' => ['nullable', 'string', 'max:2000'],
            'swotAdjustment' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'history.*' => 'realisasi', 'baseYtd' => 'YTD', 'baseMonths' => 'bulan berjalan',
            'brands.*.cells.*' => 'basis', 'brands.*.growth' => 'growth', 'ansoff.*.probability' => 'probabilitas',
            'swotAdjustment' => 'koreksi SWOT',
        ]);

        RevenueForecastPlan::updateOrCreate(['year' => $this->year], [
            'history' => collect($this->history)->map(fn ($v) => self::num($v))->all(),
            'base_ytd' => self::num($this->baseYtd),
            'base_months' => is_numeric($this->baseMonths) ? (int) $this->baseMonths : null,
            'channels' => array_values(array_map('trim', $this->channels)),
            'brands' => array_values(array_map(fn ($b) => [
                'name' => trim($b['name']),
                'cells' => array_map(fn ($v) => self::num($v), array_values($this->padCells($b['cells']))),
                'growth' => (self::num($b['growth']) ?? 0) / 100,
            ], $this->brands)),
            'ansoff' => array_values(array_map(fn ($a) => [
                'quadrant' => $a['quadrant'],
                'initiative' => trim($a['initiative']),
                'revenue' => self::num($a['revenue']),
                'probability' => (self::num($a['probability']) ?? 0) / 100,
            ], $this->ansoff)),
            'swot' => array_map(fn ($t) => trim((string) $t), $this->swot),
            'swot_adjustment' => (self::num($this->swotAdjustment) ?? 0) / 100,
            'notes' => trim($this->notes) ?: null,
        ]);

        session()->flash('message', 'Perencanaan target '.$this->year.' tersimpan.');
    }

    /**
     * Sahkan satu angka sebagai target revenue setahun (Asumsi B7). Revisi
     * yang sudah ada tidak diubah.
     */
    public function approve(string $metode): void
    {
        if ($this->lacksPermission('manage revenue')) {
            return;
        }

        if ($metode === 'manual') {
            $this->validate(['manualApproval' => ['required', 'numeric', 'gt:0']], [], ['manualApproval' => 'target disahkan']);
            $nilai = (float) $this->manualApproval;
        } else {
            $nilai = $this->compute()['methods'][$metode]['value'] ?? null;
        }

        if ($nilai === null || $nilai <= 0) {
            session()->flash('error', 'Metode itu belum menghasilkan angka — lengkapi datanya lebih dulu.');

            return;
        }

        RevenuePlan::updateOrCreate(['year' => $this->year], ['approved_target' => round($nilai, 2)]);
        $this->manualApproval = '';

        session()->flash('message', 'Target revenue '.$this->year.' disahkan: Rp '.number_format($nilai, 0, ',', '.')
            .'. Lanjutkan dengan fasing bulanan.');
    }

    /**
     * Bagian G: tulis target bulanan tahun target dari target disahkan × indeks
     * musiman. Realisasi yang sudah diisi tidak disentuh.
     */
    public function applyPhasing(): void
    {
        if ($this->lacksPermission('manage revenue')) {
            return;
        }

        $hasil = $this->compute();

        if (! $hasil['approved']) {
            session()->flash('error', 'Sahkan target revenue '.$this->year.' lebih dulu.');

            return;
        }

        $fasing = $hasil['phasing'] ?? RevenueForecast::phase($hasil['approved'], array_fill_keys(array_keys($hasil['monthly']), 1 / 12));

        DB::transaction(function () use ($fasing) {
            foreach ($fasing as $bulan => $target) {
                RevenueTarget::updateOrCreate(['period' => $this->year.'-'.$bulan], ['target' => $target]);
            }
        });

        session()->flash('message', 'Target bulanan '.$this->year.' diisi '.($hasil['phasing'] ? 'mengikuti indeks musiman '.$this->baseYear() : 'rata 12 bulan (belum ada realisasi '.$this->baseYear().')')
            .'. Lihat di menu Target Revenue.');
    }

    public function render()
    {
        return view('livewire.revenue-planning', [
            'r' => $this->compute(),
            'baseYear' => $this->baseYear(),
            'quadrants' => RevenueForecast::ANSOFF,
            'canManage' => (bool) auth()->user()?->can('manage revenue'),
            'entity' => app(EntityContext::class)->entity(),
            'years' => range((int) now()->format('Y') - 1, (int) now()->format('Y') + 3),
        ])->layout('layouts.app', ['title' => 'Perencanaan Target Revenue']);
    }
}
