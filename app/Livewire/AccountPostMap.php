<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\AccountPostRole;
use App\Models\WorkUnit;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\PostMap;
use App\Support\Bsc\RatioLibrary;
use App\Support\EntityContext;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Sheet "Peta Rasio-Akun-Dept": siapa Pemilik (O) / Kontributor (K) tiap pos
 * akun, dan — otomatis — rasio mana yang boleh diklaim tiap unit pada cascade
 * KPI. Ditetapkan Keuangan bersama CFO.
 */
class AccountPostMap extends Component
{
    use AuthorizesWrites;

    /** @var array<string, array<string, string>> unit => [pos => ''|O|K] */
    public array $cells = [];

    public function mount(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $tersimpan = PostMap::loadRoles();

        $this->cells = [];
        foreach ($this->unitCodes() as $unit) {
            foreach (array_keys(AccountPosts::all()) as $pos) {
                $this->cells[$unit][$pos] = $tersimpan[$unit][$pos] ?? '';
            }
        }
    }

    /**
     * Unit aktif, ditambah unit nonaktif yang masih punya peran supaya perannya
     * tetap terlihat dan dapat dibersihkan.
     *
     * @return array<int, string>
     */
    private function unitCodes(): array
    {
        return WorkUnit::active()->pluck('code')
            ->merge(AccountPostRole::distinct()->pluck('unit_code'))
            ->unique()->values()->all();
    }

    public function save(): void
    {
        if ($this->lacksPermission('manage ratios')) {
            return;
        }

        $sah = $this->unitCodes();
        $pos = array_keys(AccountPosts::all());

        DB::transaction(function () use ($sah, $pos) {
            foreach ($this->cells as $unit => $baris) {
                if (! in_array($unit, $sah, true)) {
                    continue;
                }

                foreach ($baris as $kode => $peran) {
                    if (! in_array($kode, $pos, true)) {
                        continue;
                    }

                    if (! in_array($peran, [AccountPostRole::PEMILIK, AccountPostRole::KONTRIBUTOR], true)) {
                        AccountPostRole::where('unit_code', $unit)->where('post_code', $kode)->delete();

                        continue;
                    }

                    AccountPostRole::updateOrCreate(
                        ['unit_code' => $unit, 'post_code' => $kode],
                        ['role' => $peran]
                    );
                }
            }
        });

        $this->load();
        session()->flash('message', 'Peta pos akun tersimpan.');
    }

    public function render()
    {
        // Pemeriksaan & bagian 3 dihitung dari isian di layar, sebelum disimpan.
        $roles = [];
        foreach ($this->cells as $unit => $baris) {
            foreach ($baris as $pos => $peran) {
                if ($peran === AccountPostRole::PEMILIK || $peran === AccountPostRole::KONTRIBUTOR) {
                    $roles[$unit][$pos] = $peran;
                }
            }
        }
        $peta = new PostMap($roles);

        $klaim = [];
        foreach (array_keys($this->cells) as $unit) {
            $klaim[$unit] = $peta->claimableRatios($unit);
        }

        return view('livewire.account-post-map', [
            'posts' => AccountPosts::all(),
            'units' => WorkUnit::whereIn('code', array_keys($this->cells))->pluck('name', 'code'),
            'ownerChecks' => $peta->ownerChecks(),
            'ratios' => RatioLibrary::all(),
            'ratioPosts' => RatioLibrary::posts(),
            'claimable' => $klaim,
            'entity' => app(EntityContext::class)->entity(),
        ])->layout('layouts.app', ['title' => 'Peta Pos Akun']);
    }
}
