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
 *   - Beberapa panduan : berkas .md dari folder docs/ (DOKUMEN di bawah), satu
 *                        berkas dipakai bersama oleh repositori dan aplikasi —
 *                        sehingga panduan di layar tidak pernah tertinggal dari
 *                        panduan di repositori;
 *   - Metode Skoring   : rumus & tabel dibaca langsung dari kode (RatioLibrary,
 *                        AccountPosts, config/bsc.php) sehingga selalu sinkron;
 *   - Uji Mandiri      : 12 pemeriksaan mesin terhadap angka workbook.
 *
 * Menambah panduan baru = menambah satu baris di DOKUMEN.
 */
class MethodDocumentation extends Component
{
    /** @var array<string, array{judul: string, berkas: string, ikon: string, ringkas: string}> */
    public const DOKUMEN = [
        'panduan' => [
            'judul' => 'Panduan Pengisian',
            'berkas' => 'docs/panduan-pengisian.md',
            'ikon' => 'fa-route',
            'ringkas' => 'Langkah mengisi aplikasi dari nol, per tingkat piramida.',
        ],
        'pengelolaan' => [
            'judul' => 'Panduan Pengelolaan',
            'berkas' => 'docs/panduan-pengelolaan.md',
            'ikon' => 'fa-screwdriver-wrench',
            'ringkas' => 'Mengurus aplikasinya: periode, peran, pengguna, unit kerja, integrasi, dan jejak audit.',
        ],
        'katalog' => [
            'judul' => 'Pos Akun & Rumus',
            'berkas' => 'docs/katalog-pos-dan-rumus.md',
            'ikon' => 'fa-sliders-h',
            'ringkas' => 'Menyesuaikan pos akun dan rumus rasio dengan keadaan entitas.',
        ],
        'odoo' => [
            'judul' => 'Integrasi Odoo',
            'berkas' => 'docs/integrasi-odoo.md',
            'ikon' => 'fa-plug',
            'ringkas' => 'Apa yang harus disiapkan di sisi Odoo agar saldonya dapat ditarik.',
        ],
    ];

    #[Url]
    public string $tab = 'panduan';

    /** @var array<int, array<string, mixed>>|null */
    public ?array $selfTest = null;

    public function switchTab(string $tab): void
    {
        $sah = array_merge(array_keys(self::DOKUMEN), ['metode', 'uji']);

        $this->tab = in_array($tab, $sah, true) ? $tab : 'panduan';
    }

    public function runSelfTest(): void
    {
        $this->selfTest = MethodSelfTest::run();
    }

    /** Isi satu panduan sebagai HTML, atau null bila berkasnya tidak ada. */
    public function document(string $tab): ?string
    {
        $dokumen = self::DOKUMEN[$tab] ?? null;

        if ($dokumen === null) {
            return null;
        }

        $berkas = base_path($dokumen['berkas']);

        // Berkas dari repositori sendiri; HTML mentah tetap dibuang dan tautan
        // berbahaya ditolak sebagai lapisan pengaman.
        return is_file($berkas)
            ? Str::markdown(file_get_contents($berkas), ['html_input' => 'strip', 'allow_unsafe_links' => false])
            : null;
    }

    public function render()
    {
        return view('livewire.method-documentation', [
            'dokumen' => self::DOKUMEN,
            'panduan' => $this->document($this->tab),
            'berkasPanduan' => self::DOKUMEN[$this->tab]['berkas'] ?? null,
            'posts' => AccountPosts::all(),
            'ratios' => RatioLibrary::all(),
            'groups' => RatioLibrary::groups(),
            'rubric' => config('bsc.rubric'),
            'apex' => config('bsc.apex_weights'),
        ])->layout('layouts.app', ['title' => 'Dokumentasi Metode']);
    }
}
