<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\AccountBalance;
use App\Models\KpiCascade;
use App\Models\KpiTest;
use App\Models\Period;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\CascadeChecks;
use App\Support\Bsc\IndicatorTest;
use App\Support\Bsc\PostMap;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use App\Support\EntityContext;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Sheet "L4 Uji Indikator": Keuangan menguji tiap KPI cascade sebelum masuk
 * monitoring — Uji A (logika sebab-akibat) dan, untuk KPI Driver, Uji B
 * (simulasi dampak ke 19 rasio). Hasil akhirnya ditulis ke status validasi.
 */
class IndicatorTests extends Component
{
    use AuthorizesWrites;

    private const MANUAL = [1, 2, 5, 6];

    #[Url]
    public string $year = '';

    #[Url(as: 'kpi')]
    public ?int $selectedId = null;

    /** @var array<int, string> Q1, Q2, Q5, Q6 → 'ya' | 'tidak' | '' */
    public array $answers = [];

    public string $period = '';

    /** Perbaikan KPI yang disimulasikan, dalam persen. */
    public string $improvement = '5';

    /** @var array<string, string> */
    public array $coefficients = [];

    /** @var array<string, string> */
    public array $coefNotes = [];

    public string $notes = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = Period::activeYear(); // tahun periode aktif di navbar
        }

        $this->resetForm();

        if ($this->selectedId) {
            $this->select($this->selectedId);
        }
    }

    public function updatedYear(): void
    {
        if (! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = now()->format('Y');
        }
        $this->selectedId = null;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->answers = array_fill_keys(self::MANUAL, '');
        $this->coefficients = array_fill_keys(array_keys(AccountPosts::all()), '');
        $this->coefNotes = array_fill_keys(array_keys(AccountPosts::all()), '');
        $this->improvement = '5';
        $this->notes = '';
        $this->period = $this->baselinePeriods()->last() ?? '';
    }

    /** Periode yang punya pos akun — calon baseline Uji B. */
    private function baselinePeriods()
    {
        return AccountBalance::query()->distinct()->orderBy('period')->pluck('period');
    }

    public function select(int $id): void
    {
        $kpi = KpiCascade::where('year', $this->year)->find($id);

        if (! $kpi) {
            $this->selectedId = null;

            return;
        }

        $this->resetForm();
        $this->resetErrorBag();
        $this->selectedId = $kpi->id;

        if ($uji = $kpi->test) {
            foreach (self::MANUAL as $q) {
                $this->answers[$q] = $uji->{'q'.$q} === null ? '' : ($uji->{'q'.$q} ? 'ya' : 'tidak');
            }
            $this->period = $uji->uji_b_period ?? $this->period;
            if ($uji->uji_b_improvement !== null) {
                $this->improvement = $this->angka($uji->uji_b_improvement * 100);
            }
            foreach ((array) $uji->uji_b_coefficients as $pos => $k) {
                if (array_key_exists($pos, $this->coefficients)) {
                    $this->coefficients[$pos] = $k === null ? '' : $this->angka((float) $k);
                }
            }
            foreach ((array) $uji->uji_b_notes as $pos => $n) {
                if (array_key_exists($pos, $this->coefNotes)) {
                    $this->coefNotes[$pos] = (string) $n;
                }
            }
            $this->notes = (string) $uji->notes;
        } elseif ($kpi->post_code && $kpi->direction) {
            // Titik awal: pos akun yang digerakkan bergerak searah klaim.
            $this->coefficients[$kpi->post_code] = $kpi->direction === 'Menurunkan' ? '-1' : '1';
        }
    }

    public function closeTest(): void
    {
        $this->selectedId = null;
        $this->resetForm();
    }

    private function angka(float $n): string
    {
        return floor($n) == $n ? number_format($n, 0, '.', '') : rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
    }

    /**
     * Seluruh perhitungan untuk KPI terpilih dari isian di layar.
     *
     * @return array<string, mixed>|null
     */
    private function evaluate(?KpiCascade $kpi, CascadeChecks $cek): ?array
    {
        if (! $kpi) {
            return null;
        }

        $baris = $cek->forRow($kpi);
        $jawaban = IndicatorTest::autoAnswers($kpi, $baris);
        foreach (self::MANUAL as $q) {
            $jawaban[$q] = match ($this->answers[$q] ?? '') {
                'ya' => true,
                'tidak' => false,
                default => null,
            };
        }
        ksort($jawaban);

        $ujiA = IndicatorTest::ujiAResult($kpi->isGuardrail(), $jawaban);

        $ujiB = null;
        $alasanB = null;
        if (! $kpi->ratio_code) {
            $alasanB = $kpi->isGuardrail()
                ? 'Guardrail tidak diarahkan ke rasio — cukup Uji A.'
                : 'KPI belum mengklaim rasio yang digerakkan.';
        } elseif ($this->period === '') {
            $alasanB = 'Belum ada periode dengan pos akun. Isi menu Pos Akun lebih dulu sebagai baseline.';
        } elseif (! is_numeric($this->improvement)) {
            $alasanB = 'Isi persentase perbaikan KPI.';
        } else {
            $mesin = app(RatioEngine::class);
            $dipakai = AccountPosts::usedValues($mesin->inputs($this->period), RatioEngine::monthOf($this->period));
            $koef = array_map(fn ($k) => is_numeric($k) ? (float) $k : 0.0, $this->coefficients);
            $ujiB = IndicatorTest::simulate($dipakai, (float) $this->improvement / 100, $koef, $kpi->ratio_code, $mesin->targetsFor($kpi->year));
        }

        return [
            'row' => $baris,
            'answers' => $jawaban,
            'ujiA' => $ujiA,
            'ujiB' => $ujiB,
            'ujiBReason' => $alasanB,
            'recommended' => IndicatorTest::recommendedStatus($kpi, $ujiA, $ujiB['result'] ?? null),
        ];
    }

    public function save(): void
    {
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        $kpi = KpiCascade::where('year', $this->year)->findOrFail($this->selectedId);

        $this->validate([
            'answers.*' => ['nullable', 'in:ya,tidak'],
            'improvement' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
            'coefficients.*' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'coefNotes.*' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['improvement' => 'perbaikan KPI', 'coefficients.*' => 'koefisien']);

        $hasil = $this->evaluate($kpi, $this->checks());

        $data = [
            'uji_a_result' => $hasil['ujiA'],
            'uji_b_period' => $hasil['ujiB'] ? $this->period : null,
            'uji_b_improvement' => $hasil['ujiB'] ? (float) $this->improvement / 100 : null,
            'uji_b_coefficients' => collect($this->coefficients)->filter(fn ($k) => is_numeric($k))->map(fn ($k) => (float) $k)->all(),
            'uji_b_notes' => collect($this->coefNotes)->filter(fn ($n) => trim($n) !== '')->all(),
            'uji_b_result' => $hasil['ujiB']['result'] ?? null,
            'notes' => trim($this->notes) ?: null,
            'tested_by' => auth()->id(),
            'tested_at' => now(),
        ];
        foreach ($hasil['answers'] as $q => $j) {
            $data['q'.$q] = $j;
        }

        KpiTest::updateOrCreate(['kpi_cascade_id' => $kpi->id], $data);

        session()->flash('message', 'Hasil uji '.$kpi->code.' tersimpan.'
            .($hasil['recommended'] ? ' Status yang dianjurkan: '.$hasil['recommended'].'.' : ''));
    }

    /** Tulis hasil akhir ke kolom "Status validasi keuangan" L3. */
    public function applyStatus(string $status): void
    {
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        if (! in_array($status, [KpiCascade::LOLOS, KpiCascade::REVISI], true)) {
            return;
        }

        $kpi = KpiCascade::where('year', $this->year)->findOrFail($this->selectedId);

        if ($status === KpiCascade::LOLOS && ! $kpi->test) {
            session()->flash('error', 'Simpan hasil uji '.$kpi->code.' lebih dulu sebelum menetapkannya Lolos.');

            return;
        }

        $uji = $kpi->test;
        $ringkas = $uji ? 'Uji A: '.($uji->uji_a_result ?? 'belum lengkap').($uji->uji_b_result ? ' · Uji B: '.$uji->uji_b_result : '') : null;

        $kpi->update([
            'validation_status' => $status,
            'finance_notes' => $ringkas ?? $kpi->finance_notes,
        ]);

        session()->flash('message', 'Status validasi '.$kpi->code.' ditetapkan: '.$status.'.');
    }

    private function checks(): CascadeChecks
    {
        return new CascadeChecks(KpiCascade::where('year', $this->year)->get(), new PostMap);
    }

    public function render()
    {
        $semua = KpiCascade::with('test')->where('year', $this->year)->get();
        $cek = new CascadeChecks($semua, new PostMap);
        $terpilih = $this->selectedId ? $semua->firstWhere('id', $this->selectedId) : null;

        return view('livewire.indicator-tests', [
            'nodes' => $cek->tree(),
            'kpi' => $terpilih,
            'result' => $this->evaluate($terpilih, $cek),
            'questions' => IndicatorTest::QUESTIONS,
            'autoQuestions' => IndicatorTest::AUTO,
            'posts' => AccountPosts::all(),
            'periods' => $this->baselinePeriods(),
            'canTest' => (bool) auth()->user()?->can('manage ratios'),
            'entity' => app(EntityContext::class)->entity(),
            'years' => range((int) now()->format('Y') - 2, (int) now()->format('Y') + 2),
            'impactName' => fn (?string $kode) => $kode ? RatioLibrary::impactName($kode) : null,
        ])->layout('layouts.app', ['title' => 'Uji Indikator']);
    }
}
