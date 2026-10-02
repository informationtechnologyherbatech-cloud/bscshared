<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Livewire\Concerns\FollowsActivePeriod;
use App\Models\AccountBalance;
use App\Models\AccountPostDefinition;
use App\Models\Period;
use App\Models\RatioDefinition;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\Formula;
use App\Support\Bsc\RatioEngine;
use App\Support\EntityContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tingkat 2 — isian pos akun satu periode (sheet "Asumsi" bagian G), beserta
 * pengelolaan katalog posnya.
 *
 * Finance mengisi nilai YTD (aliran), saldo awal tahun & saldo akhir (neraca),
 * dan data HRIS. Saat disimpan, RatioEngine menghitung rasio lalu menulis
 * hasilnya ke Rasio Keuangan — sehingga F2 di piramida ikut berubah.
 */
class AccountBalances extends Component
{
    use AuthorizesWrites;
    use FollowsActivePeriod;

    #[Url]
    public string $period = '';

    /** @var array<string, array{amount: string, opening: string}> */
    public array $values = [];

    /* ----------------------------------------- pengelolaan katalog pos akun */

    public bool $kelola = false;

    /** Kode pos yang sedang disunting; '' = pos baru, null = formulir tertutup. */
    public ?string $editing = null;

    public string $formCode = '';

    public string $formName = '';

    public string $formKind = AccountPosts::ALIRAN;

    public string $formSource = 'GL';

    public string $formHint = '';

    public function mount(): void
    {
        // Periode dari URL boleh belum dibuat (pos akun bisa diisi lebih dulu).
        $this->period = $this->validPeriod($this->period) ? $this->period : $this->initialPeriod(null);
        $this->shareActivePeriod($this->period);

        $this->loadPeriod();
    }

    public function updatedPeriod(): void
    {
        if (! $this->validPeriod($this->period)) {
            $this->period = now()->format('Y-m');
        }
        $this->shareActivePeriod($this->period);

        $this->loadPeriod();
    }

    private function validPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
    }

    private function loadPeriod(): void
    {
        $tersimpan = AccountBalance::where('period', $this->period)->get()->keyBy('code');

        // Saldo awal tahun sama untuk semua bulan dalam tahun itu, jadi bila
        // periode ini belum punya, ambil dari bulan lain di tahun yang sama.
        $saldoAwal = AccountBalance::where('period', 'like', substr($this->period, 0, 4).'-%')
            ->whereNotNull('opening')
            ->orderBy('period')
            ->get()
            ->groupBy('code')
            ->map(fn ($baris) => $baris->first()->opening);

        $this->values = [];
        foreach (array_keys(AccountPosts::all()) as $kode) {
            $baris = $tersimpan->get($kode);
            $awal = $baris?->opening ?? (AccountPosts::needsOpening($kode) ? $saldoAwal->get($kode) : null);

            $this->values[$kode] = [
                'amount' => $this->angka($baris?->amount),
                'opening' => $this->angka($awal),
            ];
        }

        $this->resetErrorBag();
    }

    private function angka(?float $nilai): string
    {
        if ($nilai === null) {
            return '';
        }

        return floor($nilai) == $nilai ? number_format($nilai, 0, '.', '') : (string) $nilai;
    }

    /** @return array<string, array{amount: float|null, opening: float|null}> */
    private function inputs(): array
    {
        $hasil = [];

        foreach (array_keys(AccountPosts::all()) as $kode) {
            $v = $this->values[$kode] ?? ['amount' => '', 'opening' => ''];
            $hasil[$kode] = [
                'amount' => is_numeric($v['amount']) ? (float) $v['amount'] : null,
                'opening' => AccountPosts::needsOpening($kode) && is_numeric($v['opening']) ? (float) $v['opening'] : null,
            ];
        }

        return $hasil;
    }

    private function isClosed(): bool
    {
        return (bool) Period::where('period', $this->period)->first()?->isClosed();
    }

    public function save(): void
    {
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        if ($this->isClosed()) {
            session()->flash('error', 'Periode '.$this->period.' telah DITUTUP (CLOSED). Pos akun tidak dapat diubah.');

            return;
        }

        $this->validate([
            'values.*.amount' => ['nullable', 'numeric'],
            'values.*.opening' => ['nullable', 'numeric'],
        ], [], [
            'values.*.amount' => 'nilai',
            'values.*.opening' => 'saldo awal',
        ]);

        $engine = app(RatioEngine::class);

        DB::transaction(function () use ($engine) {
            foreach ($this->inputs() as $kode => $v) {
                if ($v['amount'] === null && $v['opening'] === null) {
                    AccountBalance::where('period', $this->period)->where('code', $kode)->delete();

                    continue;
                }

                AccountBalance::updateOrCreate(
                    ['period' => $this->period, 'code' => $kode],
                    ['amount' => $v['amount'], 'opening' => $v['opening']]
                );
            }

            $engine->materialize($this->period);
        });

        $this->loadPeriod();
        session()->flash('message', 'Pos akun '.$this->period.' tersimpan dan rasio keuangan dihitung ulang.');
    }

    /* ----------------------------------------- pengelolaan katalog pos akun */

    public function toggleKelola(): void
    {
        $this->kelola = ! $this->kelola;
        $this->editing = null;
        $this->resetErrorBag();
    }

    /** Buka formulir untuk pos baru. */
    public function newPost(): void
    {
        $this->editing = '';
        $this->formCode = $this->nextCode();
        $this->formName = '';
        $this->formKind = AccountPosts::ALIRAN;
        $this->formSource = 'GL';
        $this->formHint = '';
        $this->resetErrorBag();
    }

    public function editPost(string $code): void
    {
        $pos = AccountPosts::catalog()[$code] ?? null;

        if ($pos === null) {
            return;
        }

        $this->editing = $code;
        $this->formCode = $code;
        $this->formName = $pos['name'];
        $this->formKind = $pos['kind'];
        $this->formSource = $pos['source'];
        $this->formHint = (string) $pos['hint'];
        $this->resetErrorBag();
    }

    public function cancelPost(): void
    {
        $this->editing = null;
        $this->resetErrorBag();
    }

    /** Kode berikutnya yang belum terpakai: PA17, PA18, … */
    private function nextCode(): string
    {
        $nomor = 1;

        foreach (array_keys(AccountPosts::catalog()) as $kode) {
            if (preg_match('/^PA(\d+)$/', $kode, $c)) {
                $nomor = max($nomor, (int) $c[1] + 1);
            }
        }

        return 'PA'.str_pad((string) $nomor, 2, '0', STR_PAD_LEFT);
    }

    public function savePost(): void
    {
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        $baru = $this->editing === '';
        $this->formCode = strtoupper(trim($this->formCode));

        $this->validate([
            'formCode' => ['required', 'regex:/^[A-Z][A-Z0-9_]{1,9}$/'],
            'formName' => ['required', 'string', 'max:255'],
            'formKind' => ['required', 'in:'.implode(',', array_keys(AccountPosts::kinds()))],
            'formSource' => ['required', 'string', 'max:20'],
            'formHint' => ['nullable', 'string', 'max:500'],
        ], [
            'formCode.regex' => 'Kode diawali huruf, lalu huruf/angka, maksimal 10 karakter — misalnya PA17.',
        ], [
            'formCode' => 'kode', 'formName' => 'nama pos', 'formKind' => 'jenis',
            'formSource' => 'sumber', 'formHint' => 'keterangan',
        ]);

        $lama = $baru ? null : AccountPostDefinition::where('code', $this->editing)->first();

        if ($baru && AccountPostDefinition::where('code', $this->formCode)->exists()) {
            $this->addError('formCode', 'Kode '.$this->formCode.' sudah dipakai pos lain.');

            return;
        }

        // Jenis pos bawaan menentukan arti angkanya di rumus bawaan, jadi dikunci.
        if ($lama?->is_builtin && $this->formKind !== $lama->kind) {
            $this->addError('formKind', 'Jenis pos bawaan tidak dapat diubah karena dipakai rumus rasio bawaan.');

            return;
        }

        AccountPostDefinition::updateOrCreate(
            ['code' => $baru ? $this->formCode : $this->editing],
            [
                'name' => $this->formName,
                'kind' => $lama?->is_builtin ? $lama->kind : $this->formKind,
                'source' => $this->formSource,
                'hint' => $this->formHint ?: null,
                'is_active' => $lama?->is_active ?? true,
                'is_builtin' => $lama?->is_builtin ?? false,
                'sort' => $lama?->sort ?? (AccountPostDefinition::max('sort') + 1),
            ]
        );

        $kode = $baru ? $this->formCode : $this->editing;
        AccountPosts::forget();
        $this->editing = null;
        $this->loadPeriod();

        session()->flash('message', 'Pos akun '.$kode.($baru ? ' ditambahkan.' : ' diperbarui.'));
    }

    /** Aktif ⇄ nonaktif. Pos yang dipakai rumus rasio tidak boleh dimatikan. */
    public function togglePost(string $code): void
    {
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        $pos = AccountPostDefinition::where('code', $code)->first();

        if ($pos === null) {
            return;
        }

        if ($pos->is_active && ($pemakai = $this->usedByRatios($code)) !== []) {
            session()->flash('error', 'Pos '.$code.' masih dipakai rumus: '.implode(', ', $pemakai)
                .'. Ubah rumus itu lebih dulu di menu Katalog Rasio.');

            return;
        }

        $pos->update(['is_active' => ! $pos->is_active]);
        AccountPosts::forget();
        $this->loadPeriod();
        $this->recompute();

        session()->flash('message', 'Pos akun '.$code.($pos->is_active ? ' diaktifkan.' : ' dinonaktifkan.'));
    }

    /** Hapus pos tambahan. Pos bawaan hanya dapat dinonaktifkan. */
    public function deletePost(string $code): void
    {
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        $pos = AccountPostDefinition::where('code', $code)->first();

        if ($pos === null || $pos->is_builtin) {
            session()->flash('error', 'Pos bawaan tidak dapat dihapus — nonaktifkan saja bila tidak dipakai.');

            return;
        }

        if (($pemakai = $this->usedByRatios($code)) !== []) {
            session()->flash('error', 'Pos '.$code.' masih dipakai rumus: '.implode(', ', $pemakai).'.');

            return;
        }

        DB::transaction(function () use ($pos, $code) {
            AccountBalance::where('code', $code)->delete();
            $pos->delete();
        });

        AccountPosts::forget();
        $this->editing = null;
        $this->loadPeriod();
        $this->recompute();

        session()->flash('message', 'Pos akun '.$code.' dihapus beserta angkanya.');
    }

    /**
     * Rasio yang rumusnya menyebut pos ini. Laba kotor (LK) & laba bersih (LB)
     * tersusun dari PA01–PA03, jadi ketiganya selalu terpakai.
     *
     * @return array<int, string>
     */
    private function usedByRatios(string $code): array
    {
        $pemakai = [];

        foreach (RatioDefinition::all() as $definisi) {
            $rumus = $definisi->expressionOrDefault();

            if (! $rumus) {
                continue;
            }

            try {
                $kode = Formula::identifiers($rumus);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $lewatTurunan = array_intersect($kode, ['LK', 'LB']) !== []
                && in_array($code, ['PA01', 'PA02', 'PA03'], true);

            if (in_array($code, $kode, true) || $lewatTurunan) {
                $pemakai[] = $definisi->code;
            }
        }

        return array_values(array_unique($pemakai));
    }

    /** Hitung ulang periode ini bila masih terbuka. */
    private function recompute(): void
    {
        if (! $this->isClosed()) {
            app(RatioEngine::class)->materialize($this->period);
        }
    }

    public function render()
    {
        // Pratinjau langsung dari isian yang sedang tampil, sebelum disimpan.
        $engine = app(RatioEngine::class);
        $hasil = $engine->evaluateWith(
            $this->inputs(),
            RatioEngine::monthOf($this->period),
            $engine->targetsFor(substr($this->period, 0, 4))
        );

        return view('livewire.account-balances', [
            'posts' => AccountPosts::all(),
            'catalog' => AccountPosts::catalog(),
            'kinds' => AccountPosts::kinds(),
            'sources' => AccountPosts::sources(),
            'hasil' => $hasil,
            'bulan' => RatioEngine::monthOf($this->period),
            'isClosed' => $this->isClosed(),
            'hasPeriod' => Period::where('period', $this->period)->exists(),
            'entity' => app(EntityContext::class)->entity(),
        ])->layout('layouts.app', ['title' => 'Pos Akun']);
    }
}
