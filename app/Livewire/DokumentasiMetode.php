<?php

namespace App\Livewire;

use Livewire\Component;

class DokumentasiMetode extends Component
{
    public $activeTab = 'skoring'; // 'skoring', 'pilar', 'dupont', 'kamus'
    public $searchQuery = '';

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        // 15 Kamus Rasio Finansial Botol Plastik
        $kamusRasio = [
            // Likuiditas
            [
                'name' => 'CURRENT RATIO',
                'category' => 'Likuiditas',
                'formula' => 'Aset Lancar / Liabilitas Jangka Pendek',
                'benchmark' => '1.5 - 2.5 X',
                'polarity' => 'Range Positif (Optimum 1.5 - 2.5x)',
                'industry_context' => 'Menjamin ketersediaan likuiditas pabrik untuk pembelian resin tunai dan pembayaran gaji operator tanpa idle cash berlebih.',
            ],
            [
                'name' => 'QUICK RATIO',
                'category' => 'Likuiditas',
                'formula' => '(Kas + Setara Kas + Piutang Dagang) / Liabilitas Lancar',
                'benchmark' => '≥ 1.0 X',
                'polarity' => 'Positif (Makin tinggi makin likuid)',
                'industry_context' => 'Kemampuan melunasi hutang segera tanpa perlu menunggu penjualan persediaan botol jadi di gudang.',
            ],
            // Solvabilitas
            [
                'name' => 'DEBT TO EQUITY RATIO',
                'category' => 'Solvabilitas',
                'formula' => 'Total Liabilitas / Total Ekuitas Modal',
                'benchmark' => '< 1.5 X',
                'polarity' => 'Negatif (Makin rendah makin sehat/aman)',
                'industry_context' => 'Membatasi leverage pengadaan mesin injeksi dan blow-molding agar tidak membebani arus kas dengan bunga pinjaman.',
            ],
            [
                'name' => 'DEBT TO TOTAL ASSET RATIO',
                'category' => 'Solvabilitas',
                'formula' => '(Total Liabilitas / Total Aset) × 100%',
                'benchmark' => '30 - 50 %',
                'polarity' => 'Range Aman (30% - 50%)',
                'industry_context' => 'Proporsi pendanaan aset pabrik (pabrik, mesin, inventory) yang dibiayai oleh kreditur vs pemegang saham.',
            ],
            // Aktivitas
            [
                'name' => 'INVENTORY TURN OVER (ITO)',
                'category' => 'Aktivitas',
                'formula' => 'Harga Pokok Penjualan (HPP) / Rata-rata Persediaan',
                'benchmark' => '≥ 8.0 X',
                'polarity' => 'Positif (Perputaran cepat meminimalkan deadstock)',
                'industry_context' => 'Kecepatan perputaran biji plastik (HDPE/PET) dan botol jadi. Menghindari degradasi resin dan penumpukan gudang.',
            ],
            [
                'name' => 'AR TURN OVER (Perputaran Piutang)',
                'category' => 'Aktivitas',
                'formula' => 'Total Penjualan / Rata-rata Piutang Usaha',
                'benchmark' => '≥ 6.0 X',
                'polarity' => 'Positif (Kolektibilitas tagihan lancar)',
                'industry_context' => 'Efektivitas penagihan invoice ke perusahaan farmasi, herbal, dan distributor botol plastik.',
            ],
            [
                'name' => 'DAYS SALES OUTSTANDING (DSO)',
                'category' => 'Aktivitas',
                'formula' => '(Piutang Usaha / Penjualan) × 360 hari',
                'benchmark' => '30 - 45 hari',
                'polarity' => 'Negatif (Semakin pendek hari penagihan semakin baik)',
                'industry_context' => 'Rata-rata hari yang dibutuhkan pembeli untuk melunasi botol yang dipesan. Target industri B2B: ≤ 45 hari.',
            ],
            [
                'name' => 'DAYS INVENTORY OUTSTANDING (DIO)',
                'category' => 'Aktivitas',
                'formula' => '(Persediaan / HPP) × 360 hari',
                'benchmark' => '30 - 45 hari',
                'polarity' => 'Negatif (Semakin sedikit hari mengendap semakin efisien)',
                'industry_context' => 'Durasi stok biji plastik dan botol kemasan siap kirim mengendap di warehouse sebelum terjual.',
            ],
            [
                'name' => 'DAYS PAYABLE OUTSTANDING (DPO)',
                'category' => 'Aktivitas',
                'formula' => '(Hutang Dagang / HPP) × 360 hari',
                'benchmark' => '30 - 60 hari',
                'polarity' => 'Positif Moderat (Memaksimalkan term pembayaran pemasok)',
                'industry_context' => 'Tenggat pembayaran ke importir/pabrik petrokimia penyedia resin plastik polimer.',
            ],
            [
                'name' => 'ASSET TURN OVER (TATO)',
                'category' => 'Aktivitas',
                'formula' => 'Total Penjualan / Total Rata-rata Aset',
                'benchmark' => '≥ 0.8 X',
                'polarity' => 'Positif (Aset pabrik menghasilkan omset tinggi)',
                'industry_context' => 'Mengukur produktivitas pabrik dan cetakan mold dalam menghasilkan pendapatan penjualan.',
            ],
            // Profitabilitas
            [
                'name' => 'GROSS PROFIT MARGIN (GPM)',
                'category' => 'Profitabilitas',
                'formula' => '((Penjualan - HPP) / Penjualan) × 100%',
                'benchmark' => '> 28.0 %',
                'polarity' => 'Positif (Efisiensi resin & biaya langsung produksi)',
                'industry_context' => 'Buffer keuntungan sebelum dikurangi beban komersial, gaji kantor, dan logistik distribusi botol.',
            ],
            [
                'name' => 'EBIT MARGIN',
                'category' => 'Profitabilitas',
                'formula' => '(Laba Operasional / Penjualan) × 100%',
                'benchmark' => '8.0 - 15.0 %',
                'polarity' => 'Positif (Kemampuan menghasilkan laba inti usaha)',
                'industry_context' => 'Menilai ketahanan laba operasional dari lini manufaktur botol plastik setelah seluruh beban pabrik & kantor.',
            ],
            [
                'name' => 'NET PROFIT MARGIN (NPM)',
                'category' => 'Profitabilitas',
                'formula' => '(Laba Bersih Setelah Pajak / Penjualan) × 100%',
                'benchmark' => '5.0 - 10.0 %',
                'polarity' => 'Positif (Persentase akhir laba bersih bagi pemegang saham)',
                'industry_context' => 'Bottom-line industri konversi plastik setelah pajak penghasilan badan dan biaya keuangan.',
            ],
            [
                'name' => 'RETURN ON ASSET (ROA)',
                'category' => 'Profitabilitas',
                'formula' => '(Laba Bersih Operasional / Total Aset) × 100%',
                'benchmark' => '≥ 8.0 %',
                'polarity' => 'Positif (Tingkat pengembalian investasi mesin)',
                'industry_context' => 'Efisiensi kapital atas aset lini mesin blow-molding dan lahan fasilitas pabrik kemasan.',
            ],
            [
                'name' => 'RETURN ON EQUITY (ROE)',
                'category' => 'Profitabilitas',
                'formula' => '(Laba Bersih / Total Ekuitas Modal) × 100%',
                'benchmark' => '> 10.0 %',
                'polarity' => 'Positif (Puncak metrik DuPont Tier 1)',
                'industry_context' => 'Hasil imbal hasil modal pemilik modal atas industri manufaktur plastik.',
            ],
        ];

        if ($this->searchQuery) {
            $q = strtolower($this->searchQuery);
            $kamusRasio = array_filter($kamusRasio, function ($item) use ($q) {
                return str_contains(strtolower($item['name']), $q) ||
                       str_contains(strtolower($item['category']), $q) ||
                       str_contains(strtolower($item['formula']), $q) ||
                       str_contains(strtolower($item['industry_context']), $q);
            });
        }

        return view('livewire.dokumentasi-metode', [
            'kamusRasio' => $kamusRasio,
        ])->layout('layouts.app', ['title' => 'Dokumentasi Metode BSC']);
    }
}
