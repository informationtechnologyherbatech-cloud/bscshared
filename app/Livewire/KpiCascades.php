<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\DepartmentObjective;
use App\Models\KpiCascade;
use App\Models\Period;
use App\Models\RevenuePlan;
use App\Models\WorkUnit;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\CascadeChecks;
use App\Support\Bsc\MonitoringSync;
use App\Support\Bsc\PostMap;
use App\Support\Bsc\RatioLibrary;
use App\Support\EntityContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Sheet "L3 Cascade KPI": KPI tahunan per unit kerja, berjenjang
 * Head (lag) → Supervisor (lead) → Staff (output), tiap jabatan Σ bobot 100%,
 * dengan penelusuran ke rasio & pos akun yang digerakkan.
 *
 * Unit kerja mengisi KPI-nya; Keuangan menetapkan status validasi. Hanya KPI
 * berstatus Lolos yang dimasukkan ke monitoring bulanan (Objective Departemen).
 */
class KpiCascades extends Component
{
    use AuthorizesWrites;

    /** Kolom isi KPI; bila salah satunya berubah, validasi Keuangan harus diulang. */
    private const CONTENT_FIELDS = [
        'unit_code', 'brand', 'level', 'parent_code', 'position', 'objective', 'measure_type',
        'target', 'unit_label', 'polarity', 'weight', 'kpi_type', 'elasticity', 'ratio_code',
        'post_code', 'direction',
    ];

    #[Url]
    public string $year = '';

    #[Url(as: 'unit')]
    public string $unitFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    /** Kode diisi otomatis sampai pengguna mengubahnya sendiri. */
    public bool $codeAuto = true;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $confirmDeleteId = null;

    public string $syncPeriod = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = Period::activeYear(); // tahun periode aktif di navbar
        }

        $this->form = $this->blankForm();
    }

    public function updatedYear(): void
    {
        if (! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = now()->format('Y');
        }
        $this->syncPeriod = '';
    }

    /** @return array<string, mixed> */
    private function blankForm(string $unit = '', string $level = KpiCascade::HEAD, string $parent = ''): array
    {
        return [
            'code' => '',
            'unit_code' => $unit,
            'brand' => '',
            'level' => $level,
            'parent_code' => $parent,
            'position' => '',
            'objective' => '',
            'measure_type' => KpiCascade::MEASURE_FOR_LEVEL[$level],
            'target' => '',
            'unit_label' => '',
            'polarity' => RatioLibrary::NAIK,
            'reporting_period' => 'Bulanan',
            'method' => '',
            'key_initiative' => '',
            'work_program' => '',
            'record' => '',
            'weight' => '',
            'kpi_type' => KpiCascade::DRIVER,
            'elasticity' => '',
            'ratio_code' => '',
            'post_code' => '',
            'direction' => '',
            'individual_type' => '',
            'validation_status' => KpiCascade::BELUM_DIUJI,
            'finance_notes' => '',
        ];
    }

    private function canWrite(): bool
    {
        return (bool) (auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi'));
    }

    private function canValidate(): bool
    {
        return (bool) auth()->user()?->can('manage ratios');
    }

    /* ------------------------------------------------------------ formulir */

    public function openCreate(string $unit = '', string $level = KpiCascade::HEAD, string $parent = ''): void
    {
        if ($this->lacksPermission('manage objectives', 'can_write_kpi')) {
            return;
        }

        $induk = $parent !== '' ? KpiCascade::where('year', $this->year)->where('code', $parent)->first() : null;

        $this->resetErrorBag();
        $this->editingId = null;
        $this->codeAuto = true;
        $this->form = $this->blankForm($unit ?: $this->unitFilter, $level, $parent);

        // KPI turunan mewarisi brand, rasio, pos akun, dan arah induknya.
        if ($induk) {
            foreach (['unit_code', 'brand', 'ratio_code', 'post_code', 'direction', 'kpi_type'] as $kolom) {
                $this->form[$kolom] = (string) ($induk->{$kolom} ?? '');
            }
        }

        $this->form['code'] = $this->suggestCode();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        if ($this->lacksPermission('manage objectives', 'can_write_kpi', 'manage ratios')) {
            return;
        }

        $kpi = KpiCascade::findOrFail($id);

        $this->resetErrorBag();
        $this->editingId = $kpi->id;
        $this->codeAuto = false;
        $this->form = $this->blankForm();
        foreach (array_keys($this->form) as $kolom) {
            $nilai = $kpi->{$kolom};
            $this->form[$kolom] = $nilai === null ? '' : (is_float($nilai) ? $this->angka($nilai) : (string) $nilai);
        }
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingId = null;
    }

    public function updatedFormCode(): void
    {
        $this->codeAuto = false;
    }

    public function updatedFormUnitCode(): void
    {
        $this->form['parent_code'] = '';
        $this->refreshCode();
    }

    public function updatedFormLevel(): void
    {
        $level = $this->form['level'];
        $this->form['measure_type'] = KpiCascade::MEASURE_FOR_LEVEL[$level] ?? $this->form['measure_type'];
        if ($level === KpiCascade::HEAD) {
            $this->form['parent_code'] = '';
        }
        if ($level !== KpiCascade::STAFF) {
            $this->form['individual_type'] = '';
        }
        $this->refreshCode();
    }

    public function updatedFormRatioCode(): void
    {
        // Pos akun yang tidak ada di rumus rasio baru dikosongkan.
        if (! in_array($this->form['post_code'], RatioLibrary::postsOf((string) $this->form['ratio_code']), true)) {
            $this->form['post_code'] = '';
        }
    }

    public function updatedFormKpiType(): void
    {
        // Guardrail tidak diarahkan ke rasio; elastisitasnya 0 (Contoh Terisi, CMP).
        if ($this->form['kpi_type'] === KpiCascade::GUARDRAIL) {
            $this->form['elasticity'] = '0';
        }
    }

    private function refreshCode(): void
    {
        if ($this->codeAuto && ! $this->editingId) {
            $this->form['code'] = $this->suggestCode();
        }
    }

    /** SCM-H01, SCM-S01, SCM-T01 … nomor berikutnya yang belum dipakai. */
    private function suggestCode(): string
    {
        $unit = strtoupper(trim((string) $this->form['unit_code']));
        $huruf = KpiCascade::CODE_LETTER[$this->form['level']] ?? 'H';

        if ($unit === '') {
            return '';
        }

        $awalan = $unit.'-'.$huruf;
        $terpakai = KpiCascade::where('year', $this->year)->where('code', 'like', $awalan.'%')->pluck('code');

        for ($n = 1; ; $n++) {
            $kode = $awalan.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            if (! $terpakai->contains($kode)) {
                return $kode;
            }
        }
    }

    public function save(): void
    {
        $bolehIsi = $this->canWrite();
        $bolehValidasi = $this->canValidate();

        if (! $bolehIsi && ! $bolehValidasi) {
            $this->lacksPermission('manage objectives');

            return;
        }

        $unitSah = WorkUnit::pluck('code')->all();
        $kodeDampak = array_merge([RatioLibrary::REVENUE], array_keys(RatioLibrary::all()));

        $data = $this->validate([
            'form.code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('kpi_cascades', 'code')
                    ->where('entity_id', app(EntityContext::class)->id())
                    ->where('year', $this->year)
                    ->ignore($this->editingId)],
            'form.unit_code' => ['required', Rule::in($unitSah)],
            'form.brand' => ['nullable', 'string', 'max:100'],
            'form.level' => ['required', Rule::in(KpiCascade::LEVELS)],
            'form.parent_code' => ['nullable', 'string', 'max:50'],
            'form.position' => ['required', 'string', 'max:150'],
            'form.objective' => ['required', 'string', 'max:255'],
            'form.measure_type' => ['required', Rule::in(['Lag', 'Lead', 'Output'])],
            'form.target' => ['nullable', 'numeric'],
            'form.unit_label' => ['nullable', 'string', 'max:50'],
            'form.polarity' => ['required', Rule::in([RatioLibrary::NAIK, RatioLibrary::TURUN, RatioLibrary::RENTANG])],
            'form.reporting_period' => ['nullable', 'string', 'max:100'],
            'form.method' => ['nullable', 'string', 'max:2000'],
            'form.key_initiative' => ['nullable', 'string', 'max:2000'],
            'form.work_program' => ['nullable', 'string', 'max:2000'],
            'form.record' => ['nullable', 'string', 'max:2000'],
            'form.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'form.kpi_type' => ['required', Rule::in([KpiCascade::DRIVER, KpiCascade::GUARDRAIL])],
            'form.elasticity' => ['nullable', 'numeric', 'min:-10', 'max:10'],
            'form.ratio_code' => ['nullable', Rule::in($kodeDampak)],
            'form.post_code' => ['nullable', Rule::in(array_keys(AccountPosts::all()))],
            'form.direction' => ['nullable', Rule::in(['Menaikkan', 'Menurunkan'])],
            'form.individual_type' => ['nullable', Rule::in(['Rutin', 'Milestone'])],
            'form.validation_status' => ['required', Rule::in(KpiCascade::STATUSES)],
            'form.finance_notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'form.code' => 'kode KPI', 'form.unit_code' => 'unit', 'form.position' => 'jabatan / PIC',
            'form.objective' => 'sasaran kerja', 'form.weight' => 'bobot', 'form.ratio_code' => 'rasio digerakkan',
            'form.post_code' => 'pos akun', 'form.target' => 'target', 'form.elasticity' => 'elastisitas',
        ])['form'];

        $nilai = collect($data)->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all();
        $nilai['code'] = strtoupper($nilai['code']);
        $nilai['unit_code'] = strtoupper($nilai['unit_code']);
        $nilai['weight'] = (float) $nilai['weight'];
        if ($nilai['level'] === KpiCascade::HEAD) {
            $nilai['parent_code'] = null;
        }

        $kpi = $this->editingId ? KpiCascade::findOrFail($this->editingId) : new KpiCascade(['year' => $this->year]);
        $kodeLama = $kpi->exists ? $kpi->code : null;

        if (! $bolehIsi) {
            // Keuangan tanpa hak isi KPI hanya menetapkan status & catatan.
            if (! $kpi->exists) {
                return;
            }
            $nilai = array_intersect_key($nilai, array_flip(['validation_status', 'finance_notes']));
        } elseif (! $bolehValidasi) {
            // Status validasi hanya ditetapkan Keuangan. Mengubah isi KPI yang
            // sudah divalidasi mengembalikannya ke "Belum diuji".
            unset($nilai['validation_status'], $nilai['finance_notes']);
            if ($kpi->exists && $this->contentChanged($kpi, $nilai)) {
                $nilai['validation_status'] = KpiCascade::BELUM_DIUJI;
            }
        }

        DB::transaction(function () use ($kpi, $nilai, $kodeLama) {
            $kpi->fill($nilai)->save();

            // Ganti kode → rujukan KPI turunan dan monitoring ikut diperbarui.
            if ($kodeLama && $kodeLama !== $kpi->code) {
                KpiCascade::where('year', $kpi->year)->where('parent_code', $kodeLama)->update(['parent_code' => $kpi->code]);
                DepartmentObjective::where('kpi_cascade_id', $kpi->id)->update(['kpi_code' => $kpi->code]);
            }
        });

        $this->showModal = false;
        $this->editingId = null;
        session()->flash('message', 'KPI '.$kpi->code.' tersimpan.'
            .($kpi->validation_status === KpiCascade::BELUM_DIUJI && $kodeLama ? ' Status validasi kembali "Belum diuji" — mohon diuji ulang oleh Keuangan.' : ''));
    }

    /** @param  array<string, mixed>  $baru */
    private function contentChanged(KpiCascade $kpi, array $baru): bool
    {
        foreach (self::CONTENT_FIELDS as $kolom) {
            $lama = $kpi->{$kolom};
            $kini = $baru[$kolom] ?? null;

            if (is_numeric($lama) || is_numeric($kini)) {
                if ($lama === null || $kini === null ? $lama !== $kini : abs((float) $lama - (float) $kini) > 1e-9) {
                    return true;
                }
            } elseif ((string) $lama !== (string) $kini) {
                return true;
            }
        }

        return false;
    }

    /* --------------------------------------------------------------- hapus */

    public function confirmDelete(int $id): void
    {
        if ($this->lacksPermission('manage objectives', 'can_write_kpi')) {
            return;
        }
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function delete(): void
    {
        if ($this->lacksPermission('manage objectives', 'can_write_kpi') || ! $this->confirmDeleteId) {
            return;
        }

        $kpi = KpiCascade::findOrFail($this->confirmDeleteId);
        $this->confirmDeleteId = null;

        $anak = KpiCascade::where('year', $kpi->year)->where('parent_code', $kpi->code)->count();
        if ($anak > 0) {
            session()->flash('error', 'KPI '.$kpi->code.' masih menjadi induk '.$anak.' KPI lain. Pindahkan atau hapus KPI turunannya lebih dulu.');

            return;
        }

        // Riwayat realisasi bulanan tetap disimpan; tautannya saja yang dilepas.
        $kpi->delete();
        session()->flash('message', 'KPI '.$kpi->code.' dihapus.');
    }

    /* ---------------------------------------------------------- monitoring */

    /**
     * Masukkan KPI berstatus Lolos ke Objective Departemen untuk satu periode.
     * Realisasi yang sudah diisi tidak disentuh; hanya definisi & target yang
     * diperbarui. Target yang dipakai adalah "Target disesuaikan" (faktor
     * revisi revenue tahun itu).
     */
    public function syncToPeriod(): void
    {
        if ($this->lacksPermission('manage objectives', 'manage ratios')) {
            return;
        }

        $periode = Period::where('period', $this->syncPeriod)->first();

        if (! $periode || ! str_starts_with($periode->period, $this->year.'-')) {
            session()->flash('error', 'Pilih periode tahun '.$this->year.' yang sudah dibuat di Piramida BSC.');

            return;
        }

        if ($periode->isClosed()) {
            session()->flash('error', 'Periode '.$periode->period.' telah DITUTUP (CLOSED). Monitoring tidak dapat diubah.');

            return;
        }

        ['created' => $baru, 'updated' => $diperbarui] = MonitoringSync::syncPeriod($periode->period);

        session()->flash('message', 'Monitoring '.$periode->period.': '.$baru.' KPI baru, '.$diperbarui.' diperbarui. '
            .'Hanya KPI berstatus Lolos yang dimasukkan; realisasi yang sudah diisi tidak berubah.');
    }

    /**
     * Sasaran di Objective Departemen tahun ini yang belum tertaut ke cascade —
     * data lama, data contoh lama, atau salinan templat periode.
     */
    private function unlinkedObjectives()
    {
        return DepartmentObjective::whereNull('kpi_cascade_id')
            ->where('period', 'like', $this->year.'-%');
    }

    /**
     * Jadikan sasaran yang belum tertaut sebagai draf KPI Head di cascade, lalu
     * tautkan. Satu KPI per kode KPI; definisinya diambil dari periode terbaru.
     * Drafnya berstatus "Belum diuji" dan sengaja belum lengkap (bobot 0, tanpa
     * rasio & pos akun) supaya pemeriksaan cascade menandai apa yang harus
     * dilengkapi unit sebelum diuji Keuangan.
     */
    public function adoptObjectives(): void
    {
        if ($this->lacksPermission('manage objectives', 'can_write_kpi')) {
            return;
        }

        $perKode = $this->unlinkedObjectives()->orderByDesc('period')->get()->groupBy('kpi_code');
        $dibuat = 0;
        $ditautkan = 0;

        DB::transaction(function () use ($perKode, &$dibuat, &$ditautkan) {
            foreach ($perKode as $kode => $baris) {
                $terbaru = $baris->first();
                $kodeKpi = $this->safeCode((string) $kode, (string) $terbaru->dept_code);

                $kpi = KpiCascade::where('year', $this->year)->where('code', $kodeKpi)->first();

                if (! $kpi) {
                    $kpi = KpiCascade::create([
                        'year' => $this->year,
                        'code' => $kodeKpi,
                        'unit_code' => strtoupper((string) $terbaru->dept_code),
                        'level' => KpiCascade::HEAD,
                        'position' => 'Kepala '.strtoupper((string) $terbaru->dept_code),
                        'objective' => mb_substr((string) $terbaru->kpi_name, 0, 255),
                        'measure_type' => 'Lag',
                        'target' => (float) $terbaru->target,
                        'polarity' => in_array($terbaru->polarity, [RatioLibrary::NAIK, RatioLibrary::TURUN, RatioLibrary::RENTANG], true)
                            ? $terbaru->polarity : RatioLibrary::NAIK,
                        'reporting_period' => 'Bulanan',
                        'weight' => 0,
                        'kpi_type' => KpiCascade::DRIVER,
                        'validation_status' => KpiCascade::BELUM_DIUJI,
                        'finance_notes' => 'Diambil dari Objective Departemen — lengkapi jabatan, bobot, rasio & pos akun, lalu uji.',
                    ]);
                    $dibuat++;
                }

                $ditautkan += DepartmentObjective::whereIn('id', $baris->pluck('id'))
                    ->update(['kpi_cascade_id' => $kpi->id, 'kpi_code' => $kpi->code]);
            }
        });

        session()->flash('message', $dibuat.' draf KPI dibuat dan '.$ditautkan.' sasaran bulanan ditautkan. '
            .'Lengkapi jabatan, bobot, rasio & pos akun yang ditandai merah, lalu minta Keuangan mengujinya.');
    }

    /** Kode KPI sah untuk cascade (huruf, angka, titik, garis); unik per tahun. */
    private function safeCode(string $kode, string $unit): string
    {
        $bersih = strtoupper(trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $kode), '-'));

        return mb_substr($bersih !== '' ? $bersih : strtoupper($unit).'-LAMA', 0, 50);
    }

    /* -------------------------------------------------------------- tampil */

    private function angka(float $n): string
    {
        return floor($n) == $n ? number_format($n, 0, '.', '') : rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
    }

    public function render()
    {
        $semua = KpiCascade::where('year', $this->year)->get();
        $peta = new PostMap;
        $cek = new CascadeChecks($semua, $peta);

        $units = WorkUnit::active()->get();
        $kodeUnit = $units->pluck('code')->merge($semua->pluck('unit_code'))->unique()->values()->all();

        $pohon = collect($cek->tree());
        if ($this->unitFilter !== '') {
            $pohon = $pohon->filter(fn ($n) => $n['row']->unit_code === $this->unitFilter);
        }

        $unitForm = strtoupper((string) ($this->form['unit_code'] ?? ''));
        $levelInduk = KpiCascade::parentLevel((string) ($this->form['level'] ?? ''));

        return view('livewire.kpi-cascades', [
            'nodes' => $pohon->values(),
            'checks' => $semua->mapWithKeys(fn ($r) => [$r->id => $cek->forRow($r)]),
            'summary' => $cek->unitSummary($kodeUnit),
            'unitNames' => $units->pluck('name', 'code'),
            'totalKpi' => $semua->count(),
            'approved' => $semua->where('validation_status', KpiCascade::LOLOS)->count(),
            'ratios' => RatioLibrary::all(),
            'posts' => AccountPosts::all(),
            'claimable' => $unitForm !== '' ? $peta->claimableRatios($unitForm) : [],
            'canClaimRevenue' => $unitForm !== '' && $peta->canClaim($unitForm, RatioLibrary::REVENUE),
            'postRoles' => $peta->roles()[$unitForm] ?? [],
            'parentOptions' => $levelInduk
                ? $semua->where('unit_code', $unitForm)->where('level', $levelInduk)->sortBy('code')
                : collect(),
            'periods' => Period::where('period', 'like', $this->year.'-%')->orderBy('period')->pluck('status', 'period'),
            'canWrite' => $this->canWrite(),
            'revisionFactor' => RevenuePlan::factorFor($this->year),
            'unlinked' => $this->unlinkedObjectives()->count(),
            'canValidate' => $this->canValidate(),
            'entity' => app(EntityContext::class)->entity(),
            'years' => range((int) now()->format('Y') - 2, (int) now()->format('Y') + 2),
        ])->layout('layouts.app', ['title' => 'Cascade KPI']);
    }
}
