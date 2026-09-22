<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\AccountBalance;
use App\Models\Period;
use App\Models\RatioDefinition;
use App\Models\RatioTarget;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use App\Support\EntityContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Katalog rasio entitas aktif: rasio mana yang dipakai, bobotnya, dan target
 * tahunannya (sheet "L2 Rasio Keuangan" kolom Bobot & Target FY).
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

    private function loadYear(): void
    {
        $definisi = RatioDefinition::all()->keyBy('code');
        $target = RatioTarget::where('year', $this->year)->pluck('target', 'code');

        $this->rows = [];
        foreach (RatioLibrary::all() as $kode => $rasio) {
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

    /** Kembalikan bobot usulan workbook untuk semua rasio. */
    public function resetWeights(): void
    {
        foreach (RatioLibrary::all() as $kode => $rasio) {
            $this->rows[$kode]['weight'] = $this->angka($rasio['weight']);
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

        $urutan = 0;
        DB::transaction(function () use (&$urutan) {
            foreach ($this->rows as $kode => $baris) {
                if (! array_key_exists($kode, RatioLibrary::all())) {
                    continue;
                }

                RatioDefinition::updateOrCreate(
                    ['code' => $kode],
                    ['weight' => (float) $baris['weight'], 'is_active' => (bool) $baris['active'], 'sort' => ++$urutan]
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

    /**
     * Hitung ulang periode tahun ini yang sudah punya pos akun, agar skor
     * tersimpan langsung memakai bobot & target baru. Periode yang sudah
     * ditutup dibiarkan apa adanya.
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
        $pustaka = RatioLibrary::all();
        $kelompok = [];

        foreach (RatioLibrary::groups() as $nama => $bobot) {
            $kelompok[$nama] = ['standard' => (float) $bobot, 'weight' => 0.0, 'count' => 0];
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
        ])->layout('layouts.app', ['title' => 'Katalog Rasio']);
    }
}
