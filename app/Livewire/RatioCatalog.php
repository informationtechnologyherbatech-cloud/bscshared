<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\AccountBalance;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\RatioDefinition;
use App\Models\RatioTarget;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\Formula;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use App\Support\EntityContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Katalog rasio entitas aktif: rasio mana yang dipakai, bobotnya, target
 * tahunannya, dan — sejak katalog menjadi data — nama serta RUMUSNYA.
 *
 * Rumus yang tersimpan di sini (kolom `expression`) adalah rumus yang benar-benar
 * dihitung mesin; yang tampil di layar bukan sekadar keterangan. Lihat
 * App\Support\Bsc\Formula.
 *
 * Tiap entitas boleh memakai susunan rasio berbeda, tetapi skornya tetap
 * bermuara ke F2 berskala 0–100 sehingga holding dapat membandingkannya.
 */
class RatioCatalog extends Component
{
    use AuthorizesWrites;

    #[Url]
    public string $year = '';

    /** @var array<string, array{active: bool, weight: string, target: string}> */
    public array $rows = [];

    /* -------------------------------------------- penyuntingan rasio & rumus */

    /** Kode rasio yang sedang disunting; '' = rasio baru, null = formulir tertutup. */
    public ?string $editing = null;

    public string $formCode = '';

    public string $formName = '';

    public string $formGroup = 'Profitabilitas';

    /** Diisi bila kelompoknya belum ada di daftar (formGroup = self::KELOMPOK_BARU). */
    public string $formGroupNew = '';

    /** Pilihan "kelompok baru" pada daftar kelompok. */
    public const KELOMPOK_BARU = '__baru__';

    public string $formUnit = 'x';

    public string $formPolarity = RatioLibrary::NAIK;

    public string $formExpression = '';

    public string $formWeight = '0';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = Period::activeYear(); // tahun periode aktif di navbar
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

    /**
     * Katalog entitas aktif. Entitas yang belum punya barisnya sendiri memakai
     * pustaka bawaan, supaya layar tetap terisi sebelum katalog dibuat.
     *
     * @return array<string, array<string, mixed>>
     */
    private function catalog(): array
    {
        $definisi = RatioDefinition::orderBy('sort')->orderBy('code')->get();

        if ($definisi->isEmpty()) {
            return array_map(
                fn (array $r) => $r + ['builtin' => true, 'custom_weight' => $r['weight']],
                RatioLibrary::all()
            );
        }

        $katalog = [];

        foreach ($definisi as $d) {
            $katalog[$d->code] = $d->meta() + [
                'builtin' => (bool) $d->is_builtin,
                'custom_weight' => $d->weight,
            ];
        }

        return $katalog;
    }

    private function loadYear(): void
    {
        $definisi = RatioDefinition::all()->keyBy('code');
        $target = RatioTarget::where('year', $this->year)->pluck('target', 'code');

        $this->rows = [];
        foreach ($this->catalog() as $kode => $rasio) {
            $d = $definisi->get($kode);
            $this->rows[$kode] = [
                'active' => $d ? $d->is_active : false,
                'weight' => $this->angka($d ? $d->weight : $rasio['weight']),
                'target' => $this->angka($target->get($kode)),
            ];
        }

        $this->resetErrorBag();
    }

    private function angka(mixed $nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return '';
        }

        $n = (float) $nilai;

        return floor($n) == $n ? number_format($n, 0, '.', '') : rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
    }

    /** Kembalikan bobot usulan workbook untuk rasio bawaan. */
    public function resetWeights(): void
    {
        foreach (RatioLibrary::all() as $kode => $rasio) {
            if (isset($this->rows[$kode])) {
                $this->rows[$kode]['weight'] = $this->angka($rasio['weight']);
            }
        }
    }

    public function save(): void
    {
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        $this->validate([
            'rows.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'rows.*.target' => ['nullable', 'numeric'],
        ], [], [
            'rows.*.weight' => 'bobot',
            'rows.*.target' => 'target',
        ]);

        $katalog = $this->catalog();
        $urutan = 0;

        DB::transaction(function () use (&$urutan, $katalog) {
            foreach ($this->rows as $kode => $baris) {
                if (! array_key_exists($kode, $katalog)) {
                    continue;
                }

                $rasio = $katalog[$kode];

                RatioDefinition::updateOrCreate(
                    ['code' => $kode],
                    [
                        'weight' => (float) $baris['weight'],
                        'is_active' => (bool) $baris['active'],
                        'sort' => ++$urutan,
                        // Baris yang baru dibuat di sini (entitas tanpa katalog)
                        // ikut membawa rumusnya, bukan hanya bobot.
                        'name' => $rasio['name'],
                        'ratio_group' => $rasio['group'],
                        'formula' => $rasio['formula'],
                        'expression' => $rasio['expression'] ?? null,
                        'unit' => $rasio['unit'],
                        'polarity' => $rasio['polarity'],
                        'is_builtin' => $rasio['builtin'] ?? true,
                    ]
                );

                if ($baris['target'] === '' || $baris['target'] === null) {
                    RatioTarget::where('year', $this->year)->where('code', $kode)->delete();
                } else {
                    RatioTarget::updateOrCreate(
                        ['year' => $this->year, 'code' => $kode],
                        ['target' => (float) $baris['target']]
                    );
                }
            }
        });

        [$dihitung, $dilewati] = $this->recompute();

        $this->loadYear();

        $pesan = 'Katalog rasio tersimpan.';
        if ($dihitung) {
            $pesan .= ' Rasio periode '.implode(', ', $dihitung).' dihitung ulang.';
        }
        if ($dilewati) {
            $pesan .= ' Periode ditutup tidak diubah: '.implode(', ', $dilewati).'.';
        }
        session()->flash('message', $pesan);
    }

    /* -------------------------------------------- penyuntingan rasio & rumus */

    public function newRatio(): void
    {
        $this->editing = '';
        $this->formCode = $this->nextCode();
        $this->formName = '';
        $this->formGroup = array_key_first(RatioLibrary::groups());
        $this->formGroupNew = '';
        $this->formUnit = 'x';
        $this->formPolarity = RatioLibrary::NAIK;
        $this->formExpression = '';
        $this->formWeight = '0';
        $this->resetErrorBag();
    }

    public function editRatio(string $code): void
    {
        $rasio = $this->catalog()[$code] ?? null;

        if ($rasio === null) {
            return;
        }

        $this->editing = $code;
        $this->formCode = $code;
        $this->formName = $rasio['name'];
        $this->formGroup = $rasio['group'];
        $this->formGroupNew = '';
        $this->formUnit = $rasio['unit'];
        $this->formPolarity = $rasio['polarity'];
        $this->formExpression = (string) ($rasio['expression'] ?? '');
        $this->formWeight = $this->angka($this->rows[$code]['weight'] ?? 0);
        $this->resetErrorBag();
    }

    public function cancelRatio(): void
    {
        $this->editing = null;
        $this->resetErrorBag();
    }

    /** Kode berikutnya untuk rasio buatan sendiri: R1, R2, … */
    private function nextCode(): string
    {
        $nomor = 1;

        foreach (array_keys($this->catalog()) as $kode) {
            if (preg_match('/^R(\d+)$/', $kode, $c)) {
                $nomor = max($nomor, (int) $c[1] + 1);
            }
        }

        return 'R'.$nomor;
    }

    /** Kode yang boleh dipakai di dalam rumus. */
    private function knownCodes(): array
    {
        return array_merge(
            array_keys(AccountPosts::all()),
            ['LK', 'LB'],
            array_keys($this->catalog())
        );
    }

    public function saveRatio(): void
    {
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        $baru = $this->editing === '';
        $this->formCode = strtoupper(trim($this->formCode));

        // "Kelompok baru…" dipakai: yang berlaku adalah isian di sebelahnya.
        if ($this->formGroup === self::KELOMPOK_BARU) {
            $this->validate(
                ['formGroupNew' => ['required', 'string', 'max:50']],
                ['formGroupNew.required' => 'Nama kelompok barunya belum diisi.'],
                ['formGroupNew' => 'kelompok baru']
            );
            $this->formGroup = trim($this->formGroupNew);
        }

        $this->validate([
            'formCode' => ['required', 'regex:/^[A-Z][A-Z0-9_]{0,9}$/'],
            'formName' => ['required', 'string', 'max:255'],
            'formGroup' => ['required', 'string', 'max:50'],
            'formUnit' => ['required', 'string', 'max:10'],
            'formPolarity' => ['required', 'in:'.RatioLibrary::NAIK.','.RatioLibrary::TURUN.','.RatioLibrary::RENTANG],
            'formWeight' => ['required', 'numeric', 'min:0', 'max:100'],
            'formExpression' => ['required', 'string', 'max:500'],
        ], [
            'formCode.regex' => 'Kode diawali huruf, lalu huruf/angka, maksimal 10 karakter — misalnya R1.',
        ], [
            'formCode' => 'kode', 'formName' => 'nama rasio', 'formGroup' => 'kelompok',
            'formUnit' => 'satuan', 'formPolarity' => 'polaritas', 'formWeight' => 'bobot',
            'formExpression' => 'rumus',
        ]);

        if ($baru && array_key_exists($this->formCode, $this->catalog())) {
            $this->addError('formCode', 'Kode '.$this->formCode.' sudah dipakai rasio lain.');

            return;
        }

        $kode = $baru ? $this->formCode : $this->editing;

        // Rumus tidak boleh menunjuk dirinya sendiri, langsung maupun lewat rasio lain.
        $galat = Formula::validate($this->formExpression, $this->knownCodes());

        if ($galat === null && in_array($kode, Formula::identifiers($this->formExpression), true)) {
            $galat = 'Rumus tidak boleh memakai kodenya sendiri ('.$kode.').';
        }

        if ($galat !== null) {
            $this->addError('formExpression', $galat);

            return;
        }

        $lama = $baru ? null : RatioDefinition::where('code', $kode)->first();

        RatioDefinition::updateOrCreate(
            ['code' => $kode],
            [
                'name' => $this->formName,
                'ratio_group' => $this->formGroup,
                // Teks yang dibaca manusia dibangun dari rumusnya sendiri, jadi
                // keduanya tidak mungkin berbeda arti.
                'formula' => $this->humanFormula($this->formExpression),
                'expression' => Formula::normalise($this->formExpression),
                'unit' => $this->formUnit,
                'polarity' => $this->formPolarity,
                'weight' => (float) $this->formWeight,
                'is_active' => $lama?->is_active ?? true,
                'is_builtin' => $lama?->is_builtin ?? false,
                'sort' => $lama?->sort ?? ((int) RatioDefinition::max('sort') + 1),
            ]
        );

        $this->editing = null;
        $this->loadYear();
        [$dihitung] = $this->recompute();

        session()->flash('message', 'Rasio '.$kode.' '.($baru ? 'ditambahkan' : 'diperbarui')
            .($dihitung ? '; periode '.implode(', ', $dihitung).' dihitung ulang.' : '.'));
    }

    /** Hapus rasio buatan sendiri beserta target & hasil hitungannya. */
    public function deleteRatio(string $code): void
    {
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        $definisi = RatioDefinition::where('code', $code)->first();

        if ($definisi === null || $definisi->is_builtin) {
            session()->flash('error', 'Rasio bawaan tidak dapat dihapus — nonaktifkan saja bila tidak dipakai.');

            return;
        }

        if (($pemakai = $this->usedByRatios($code)) !== []) {
            session()->flash('error', 'Rasio '.$code.' masih dipakai rumus: '.implode(', ', $pemakai).'.');

            return;
        }

        DB::transaction(function () use ($definisi, $code) {
            RatioTarget::where('code', $code)->delete();
            FinancialRatio::where('ratio_code', $code)->delete();
            $definisi->delete();
        });

        $this->editing = null;
        $this->loadYear();
        $this->recompute();

        session()->flash('message', 'Rasio '.$code.' dihapus.');
    }

    /**
     * Rasio lain yang rumusnya menyebut kode ini.
     *
     * @return array<int, string>
     */
    private function usedByRatios(string $code): array
    {
        $pemakai = [];

        foreach ($this->catalog() as $kode => $rasio) {
            if ($kode === $code || ! ($rasio['expression'] ?? null)) {
                continue;
            }

            try {
                if (in_array($code, Formula::identifiers($rasio['expression']), true)) {
                    $pemakai[] = $kode;
                }
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return $pemakai;
    }

    /**
     * Rumus yang sedang diketik, dibaca memakai nama pos akun — ditampilkan
     * hidup di bawah kotak rumus, supaya terlihat kode mana berarti apa.
     */
    public function readable(): string
    {
        if ($this->editing === null || trim($this->formExpression) === '') {
            return '';
        }

        if (Formula::validate($this->formExpression, $this->knownCodes()) !== null) {
            return '';
        }

        return $this->humanFormula($this->formExpression);
    }

    /**
     * Sisipkan sebuah kode/operator ke ujung rumus.
     *
     * Dikerjakan di sisi server, bukan dengan JavaScript yang menulis langsung
     * ke kotak isian: Livewire ikut mengirim isi kotak yang belum tersimpan pada
     * permintaan yang sama, sehingga yang sedang diketik tidak tertimpa.
     */
    public function insertCode(string $code): void
    {
        $sekarang = rtrim($this->formExpression);

        $rapat = $code === ')' || $sekarang === '' || str_ends_with($sekarang, '(');

        $this->formExpression = $sekarang.($rapat ? '' : ' ').$code;
    }

    /**
     * Kode yang dapat disisipkan ke rumus, beserta namanya — pengisi tidak
     * perlu menghafal PA01 dan seterusnya.
     *
     * @return array<string, array{name: string, hint: string, group: string}>
     */
    public function codeHelp(): array
    {
        $daftar = [
            'LK' => ['name' => 'Laba kotor', 'hint' => 'Penjualan − HPP', 'group' => 'Turunan'],
            'LB' => ['name' => 'Laba bersih', 'hint' => 'Laba kotor − Beban usaha', 'group' => 'Turunan'],
        ];

        foreach (AccountPosts::all() as $kode => $pos) {
            $daftar[$kode] = ['name' => $pos['name'], 'hint' => (string) $pos['hint'], 'group' => 'Pos akun'];
        }

        foreach ($this->catalog() as $kode => $rasio) {
            if ($kode !== $this->editing) {
                $daftar[$kode] = ['name' => $rasio['name'], 'hint' => $rasio['formula'], 'group' => 'Rasio lain'];
            }
        }

        return $daftar;
    }

    /** Rumus dengan kode diganti nama pos akun, untuk ditampilkan di bawah nama rasio. */
    private function humanFormula(string $expression): string
    {
        $nama = ['LK' => 'Laba kotor', 'LB' => 'Laba bersih'];

        foreach (AccountPosts::catalog() as $kode => $pos) {
            $nama[$kode] = $pos['name'];
        }

        foreach ($this->catalog() as $kode => $rasio) {
            $nama[$kode] ??= $rasio['name'];
        }

        try {
            return Formula::humanise($expression, $nama);
        } catch (\InvalidArgumentException) {
            return $expression;
        }
    }

    /**
     * Pratinjau rumus memakai angka periode terakhir yang sudah terisi, supaya
     * rumus dapat dicoba sebelum disimpan — tanpa kalkulator.
     *
     * @return array{period: string|null, value: float|null, arithmetic: string, error: string|null}
     */
    public function preview(): array
    {
        $kosong = ['period' => null, 'value' => null, 'arithmetic' => '', 'error' => null];

        if ($this->editing === null || trim($this->formExpression) === '') {
            return $kosong;
        }

        if ($galat = Formula::validate($this->formExpression, $this->knownCodes())) {
            return ['period' => null, 'value' => null, 'arithmetic' => '', 'error' => $galat];
        }

        $periode = AccountBalance::where('period', 'like', $this->year.'-%')->max('period')
            ?? AccountBalance::max('period');

        if ($periode === null) {
            return $kosong;
        }

        $mesin = app(RatioEngine::class);
        $dipakai = AccountPosts::usedValues($mesin->inputs($periode), RatioEngine::monthOf($periode));

        $rumus = RatioLibrary::expressions();
        foreach ($this->catalog() as $kode => $rasio) {
            if ($rasio['expression'] ?? null) {
                $rumus[$kode] = $rasio['expression'];
            }
        }

        try {
            return [
                'period' => $periode,
                'value' => Formula::evaluate($this->formExpression, $dipakai, Formula::resolver($rumus, $dipakai)),
                'arithmetic' => Formula::substitute($this->formExpression, $dipakai),
                'error' => null,
            ];
        } catch (\InvalidArgumentException $e) {
            return ['period' => null, 'value' => null, 'arithmetic' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Hitung ulang periode tahun ini yang sudah punya pos akun, agar skor
     * tersimpan langsung memakai bobot, target, dan rumus baru. Periode yang
     * sudah ditutup dibiarkan apa adanya.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function recompute(): array
    {
        $periode = AccountBalance::where('period', 'like', $this->year.'-%')
            ->distinct()->orderBy('period')->pluck('period');
        $ditutup = Period::whereIn('period', $periode)->get()->filter->isClosed()->pluck('period')->all();

        $engine = app(RatioEngine::class);
        $dihitung = [];

        foreach ($periode as $p) {
            if (in_array($p, $ditutup, true)) {
                continue;
            }
            $engine->materialize($p);
            $dihitung[] = $p;
        }

        return [$dihitung, $ditutup];
    }

    public function render()
    {
        $pustaka = $this->catalog();
        $kelompok = [];

        foreach (RatioLibrary::groups() as $nama => $bobot) {
            $kelompok[$nama] = ['standard' => (float) $bobot, 'weight' => 0.0, 'count' => 0];
        }

        // Kelompok buatan sendiri ikut tampil, tanpa acuan bawaan.
        foreach ($pustaka as $rasio) {
            $kelompok[$rasio['group']] ??= ['standard' => 0.0, 'weight' => 0.0, 'count' => 0];
        }

        $totalBobot = 0.0;
        $target = [];

        foreach ($this->rows as $kode => $baris) {
            $target[$kode] = is_numeric($baris['target']) ? (float) $baris['target'] : null;

            if (! $baris['active'] || ! is_numeric($baris['weight'])) {
                continue;
            }

            $grup = $pustaka[$kode]['group'] ?? null;
            $totalBobot += (float) $baris['weight'];

            if (isset($kelompok[$grup])) {
                $kelompok[$grup]['weight'] += (float) $baris['weight'];
                $kelompok[$grup]['count']++;
            }
        }

        // Rasio nonaktif tidak ikut cek konsistensi target.
        foreach ($this->rows as $kode => $baris) {
            if (! $baris['active']) {
                $target[$kode] = null;
            }
        }

        return view('livewire.ratio-catalog', [
            'library' => $pustaka,
            'groups' => $kelompok,
            'totalWeight' => $totalBobot,
            'checks' => RatioEngine::consistencyChecks($target, $totalBobot),
            'entity' => app(EntityContext::class)->entity(),
            'years' => range((int) now()->format('Y') - 3, (int) now()->format('Y') + 2),
            'posts' => AccountPosts::all(),
            'groupNames' => array_keys($kelompok),
            'preview' => $this->preview(),
            'readable' => $this->readable(),
            'codeHelp' => $this->codeHelp(),
        ])->layout('layouts.app', ['title' => 'Katalog Rasio']);
    }
}
