<?php

namespace App\Livewire;

use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\MethodSelfTest;
use App\Support\Bsc\RatioLibrary;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Menu "Dokumentasi Metode":
 *   - Panduan Pengisian : docs/panduan-pengisian.md (satu berkas untuk repo & aplikasi)
 *   - Metode Skoring    : rumus & tabel dibaca langsung dari kode (RatioLibrary,
 *                         AccountPosts, config/bsc.php) sehingga selalu sinkron
 *   - Uji Mandiri       : 12 pemeriksaan mesin terhadap angka workbook
 */
class MethodDocumentation extends Component
{
    #[Url]
    public string $tab = 'panduan';

    /** @var array<int, array<string, mixed>>|null */
    public ?array $selfTest = null;

    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, ['panduan', 'metode', 'uji'], true) ? $tab : 'panduan';
    }

    public function runSelfTest(): void
    {
        $this->selfTest = MethodSelfTest::run();
    }

    public function render()
    {
        $berkas = base_path('docs/panduan-pengisian.md');

        return view('livewire.method-documentation', [
            // Berkas dari repositori sendiri; HTML mentah tetap dibuang dan tautan
            // berbahaya ditolak sebagai lapisan pengaman.
            'panduan' => is_file($berkas)
                ? Str::markdown(file_get_contents($berkas), ['html_input' => 'strip', 'allow_unsafe_links' => false])
                : null,
            'posts' => AccountPosts::all(),
            'ratios' => RatioLibrary::all(),
            'groups' => RatioLibrary::groups(),
            'rubric' => config('bsc.rubric'),
            'apex' => config('bsc.apex_weights'),
        ])->layout('layouts.app', ['title' => 'Dokumentasi Metode']);
    }
}
