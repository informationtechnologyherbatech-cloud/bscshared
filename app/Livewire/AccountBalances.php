<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Livewire\Concerns\FollowsActivePeriod;
use App\Models\AccountBalance;
use App\Models\Period;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\RatioEngine;
use App\Support\EntityContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tingkat 2 — isian 16 pos akun satu periode (sheet "Asumsi" bagian G).
 *
 * Finance mengisi nilai YTD (aliran), saldo awal tahun & saldo akhir (neraca),
 * dan data HRIS. Saat disimpan, RatioEngine menghitung 19 rasio lalu menulis
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
            'hasil' => $hasil,
            'bulan' => RatioEngine::monthOf($this->period),
            'isClosed' => $this->isClosed(),
            'hasPeriod' => Period::where('period', $this->period)->exists(),
            'entity' => app(EntityContext::class)->entity(),
        ])->layout('layouts.app', ['title' => 'Pos Akun']);
    }
}
