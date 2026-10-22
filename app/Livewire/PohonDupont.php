<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\FinancialRatio;
use App\Models\DepartmentObjective;
use App\Models\Period;

class PohonDupont extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    #[Url(as: 'view')]
    public $viewMode = 'visual'; // 'visual', 'schematic', 'table'

    // Driver Finansial & Operasional (dalam Miliar IDR atau Persen / Hari)
    public $penjualan = 100.0; // IDR 100 Miliar
    public $gpm = 30.0; // 30% Gross Profit Margin
    public $bebanUsahaRatio = 12.0; // 12% Beban Usaha / Penjualan
    public $bungaRatio = 1.5; // 1.5% Bunga / Penjualan
    public $pajakRatio = 1.7; // 1.7% Pajak / Penjualan

    // Working Capital & Cash Conversion Cycle Drivers
    public $termKreditS5 = 30.0; // Hari (S5)
    public $hariTerlambatC1 = 15.0; // Hari (C1)

    public $dioBahanBakuI1 = 25.0; // Hari (I1)
    public $dioWipP6 = 10.0; // Hari (P6)
    public $dioBarangJadiI2 = 15.0; // Hari (I2)

    public $termSupplierB2 = 45.0; // Hari (B2)
    public $hariBayarCepat = 10.0; // Hari

    // Komponen Neraca Lainnya (Miliar IDR)
    public $kasIdle = 4.0;
    public $asetTetap = 50.0;
    public $utangBerbunga = 25.0;
    public $kewajibanLancarLain = 5.0;

    // Filter kode terpilih untuk modal/pop-up detail driver
    public $selectedKode = null;

    // Kamus Driver Indikator Operasional Sesuai Bagan
    public $kpiDictionary = [
        // GPM Driver
        'S1' => ['nama' => 'Volume & Mix Penjualan Produk Utama', 'dept' => 'Sales & Mkt', 'polaritas' => 'Naik', 'dampak' => 'Meningkatkan omset kotor dan mengoptimalkan margin laba per unit'],
        'S4' => ['nama' => 'Diskon & Kebijakan Harga Jual Pasar', 'dept' => 'Marketing', 'polaritas' => 'Turun', 'dampak' => 'Mengurangi diskon liar untuk menjaga margin kotor penjualan'],
        'B1' => ['nama' => 'Harga Beli Biji Plastik / Resin (Bahan Baku)', 'dept' => 'Procurement', 'polaritas' => 'Turun', 'dampak' => 'Menekan biaya HPP langsung sehingga Gross Profit Margin meningkat'],
        'P1' => ['nama' => 'Scrap Rate & Yield Losses Botol Plastik', 'dept' => 'Produksi', 'polaritas' => 'Turun', 'dampak' => 'Mengurangi afval/cacat bahan resin, menurunkan HPP riil'],
        'P3' => ['nama' => 'Efisiensi Waktu Setup & Siklus Mesin', 'dept' => 'Produksi', 'polaritas' => 'Turun', 'dampak' => 'Menekan biaya overhead pabrik per unit botol'],
        'P4' => ['nama' => 'Availability Mesin & OEE Pabrik', 'dept' => 'Engineering', 'polaritas' => 'Naik', 'dampak' => 'Memaksimalkan kapasitas terpasang dan efisiensi biaya konversi'],
        'P5' => ['nama' => 'Produktivitas Output Jam Kerja Langsung', 'dept' => 'Produksi', 'polaritas' => 'Naik', 'dampak' => 'Mengurangi biaya tenaga kerja per satuan botol'],
        'M1' => ['nama' => 'Preventive Maintenance & MTBF Mesin', 'dept' => 'Maintenance', 'polaritas' => 'Naik', 'dampak' => 'Mencegah breakdown mendadak yang memboroskan sparepart dan energi'],
        'Q1' => ['nama' => 'Defect Rate Mutu & Cost of Poor Quality (COPQ)', 'dept' => 'Quality Control', 'polaritas' => 'Turun', 'dampak' => 'Menghilangkan retur pelanggan dan biaya penanganan produk gagal'],

        // Beban Usaha Driver
        'G1' => ['nama' => 'Efisiensi Listrik, Air & Utilitas Pabrik', 'dept' => 'General Affair', 'polaritas' => 'Turun', 'dampak' => 'Menekan pos beban operasional umum pabrik terhadap omset'],
        'G2' => ['nama' => 'Pengendalian Biaya Operasional & Rumah Tangga', 'dept' => 'General Affair', 'polaritas' => 'Turun', 'dampak' => 'Mengendalikan belanja rutin non-produksi'],
        'L2' => ['nama' => 'Efisiensi Tarif Logistik & Armada Truk', 'dept' => 'Logistik & SCM', 'polaritas' => 'Turun', 'dampak' => 'Menurunkan beban biaya distribusi dan ongkos angkut penjualan'],
        'Q2' => ['nama' => 'Biaya Inspeksi Lab & Audit Kualitas Eksternal', 'dept' => 'Quality Assurance', 'polaritas' => 'Turun', 'dampak' => 'Mengoptimalkan biaya pengujian laboratorium mandiri'],
        'C4' => ['nama' => 'Biaya Administrasi Penagihan & Legalitas', 'dept' => 'Finance & Legal', 'polaritas' => 'Turun', 'dampak' => 'Mencegah biaya tambahan somasi atau legal penagihan piutang'],

        // Piutang (AR) Driver
        'S5' => ['nama' => 'Term Kredit Penjualan Distributor (Term of Payment)', 'dept' => 'Sales', 'polaritas' => 'Turun', 'dampak' => 'Menentukan baseline jumlah hari kredit piutang (DSO)'],
        'C1' => ['nama' => 'Rata-rata Keterlambatan Pembayaran Pelanggan (Overdue)', 'dept' => 'Collection / AR', 'polaritas' => 'Turun', 'dampak' => 'Mempercepat arus kas masuk dan mengurangi saldo piutang tertahan'],
        'C2' => ['nama' => 'Penyisihan Piutang Tak Tertagih (Bad Debt)', 'dept' => 'Collection', 'polaritas' => 'Turun', 'dampak' => 'Menjaga kualitas piutang tetap lancar dan sehat'],
        'C3' => ['nama' => 'Kecepatan Rekonsiliasi & Konfirmasi Tagihan', 'dept' => 'Accounting', 'polaritas' => 'Naik', 'dampak' => 'Mencegah sengketa faktur yang memperlambat pembayaran'],
        'L1' => ['nama' => 'Lead Time Pengiriman DO Hingga Tukar Faktur', 'dept' => 'Logistik', 'polaritas' => 'Turun', 'dampak' => 'Mempercepat mulainya perhitungan jatuh tempo kredit'],

        // Persediaan (Inventory) Driver
        'I1' => ['nama' => 'DIO Bahan Baku (Biji Plastik / Resin)', 'dept' => 'Gudang Bahan', 'polaritas' => 'Turun', 'dampak' => 'Mengurangi modal kerja yang tertimbun di silo biji plastik'],
        'P6' => ['nama' => 'DIO Barang Dalam Proses (WIP Botol & Preform)', 'dept' => 'Produksi', 'polaritas' => 'Turun', 'dampak' => 'Mempercepat siklus botol dari preform ke botol jadi'],
        'I2' => ['nama' => 'DIO Barang Jadi Botol Plastik di Gudang', 'dept' => 'Gudang Distribusi', 'polaritas' => 'Turun', 'dampak' => 'Menghindari penumpukan stok botol jadi tanpa order'],
        'I3' => ['nama' => 'Akurasi Stok Fisik vs Kartu Stok Odoo', 'dept' => 'Inventory Control', 'polaritas' => 'Naik', 'dampak' => 'Mencegah selisih persediaan dan pemborosan pemesanan ulang'],
        'I4' => ['nama' => 'Tingkat Kerusakan Barang di Gudang (Warehouse Scrap)', 'dept' => 'Gudang', 'polaritas' => 'Turun', 'dampak' => 'Mencegah write-off persediaan rusak'],
        'I5' => ['nama' => 'Pengendalian Slow Moving & Dead Stock', 'dept' => 'SCM', 'polaritas' => 'Turun', 'dampak' => 'Mempercepat perputaran barang dan likuiditas persediaan'],
        'S3' => ['nama' => 'Akurasi Forecast Penjualan & Buffer Stock', 'dept' => 'PPIC / Demand', 'polaritas' => 'Naik', 'dampak' => 'Mencegah overstock akibat salah perkiraan permintaan'],
        'B4' => ['nama' => 'Ukuran Minimum Order (MOQ) Pembelian Bahan', 'dept' => 'Procurement', 'polaritas' => 'Turun', 'dampak' => 'Mencegah pembelian berlebih hanya demi mengejar diskon kuantiti'],

        // Kas Idle Driver
        'F1' => ['nama' => 'Cash Flow Matching & Saldo Kas Minimum Operasional', 'dept' => 'Treasury', 'polaritas' => 'Naik', 'dampak' => 'Memastikan likuiditas cukup tanpa membiarkan kas menganggur'],
        'F2' => ['nama' => 'Optimalisasi Imbal Hasil Rekening & Deposito Jangka Pendek', 'dept' => 'Finance', 'polaritas' => 'Naik', 'dampak' => 'Memaksimalkan pendapatan bunga dari dana sisa'],

        // Aset Tetap Driver
        'P2' => ['nama' => 'Kapasitas Terpasang Mesin Blow & Injection', 'dept' => 'Engineering', 'polaritas' => 'Naik', 'dampak' => 'Memaksimalkan perputaran aset tetap (Fixed Asset Turnover)'],
        'F8' => ['nama' => 'Kelayakan Investasi Capex Mesin Baru (ROI / Payback)', 'dept' => 'Finance & Direksi', 'polaritas' => 'Naik', 'dampak' => 'Memastikan penambahan aset tetap sebanding dengan tambahan omset'],

        // Utang Usaha (AP) Driver
        'B2' => ['nama' => 'Term of Payment Supplier Biji Plastik & Moulding', 'dept' => 'Procurement', 'polaritas' => 'Naik', 'dampak' => 'Memperpanjang DPO secara sehat untuk mendanai operasional'],
        'B3' => ['nama' => 'Disiplin Waktu Pembayaran Sesuai Tanggal Jatuh Tempo', 'dept' => 'Account Payable', 'polaritas' => 'Naik', 'dampak' => 'Menghindari pembayaran terlalu awal agar kas tetap optimal'],

        // Utang Berbunga Driver
        'F3' => ['nama' => 'Plafon Kredit Modal Kerja (KMK) Bank', 'dept' => 'Finance', 'polaritas' => 'Turun', 'dampak' => 'Mengendalikan eksposur kewajiban berbunga jangka pendek'],
        'F4' => ['nama' => 'Negosiasi Suku Bunga Pinjaman Bank', 'dept' => 'Finance', 'polaritas' => 'Turun', 'dampak' => 'Menurunkan beban bunga sehingga Net Profit Margin meningkat'],
        'F5' => ['nama' => 'Leasing Pembiayaan Mesin Baru', 'dept' => 'Finance', 'polaritas' => 'Turun', 'dampak' => 'Mengoptimalkan leverage pembiayaan modal kerja'],
        'F6' => ['nama' => 'Struktur Rasio Solvabilitas & Debt Service Coverage', 'dept' => 'Finance', 'polaritas' => 'Naik', 'dampak' => 'Menjaga solvabilitas perusahaan pada tingkat yang aman'],

        // Ekuitas Driver
        'F7' => ['nama' => 'Kebijakan Dividen Payout & Akumulasi Laba Ditahan', 'dept' => 'Manajemen / Direksi', 'polaritas' => 'Naik', 'dampak' => 'Memperkuat struktur permodalan internal jangka panjang'],
    ];

    public function mount()
    {
        $this->selectedPeriod = request()->query('period') ?? session('active_period', '2026-08');
        $this->loadFromDatabase();
    }

    public function loadFromDatabase()
    {
        // Mencari rasio keuangan aktual jika ada di tabel financial_ratios
        $ratios = FinancialRatio::where('period', $this->selectedPeriod)->get();

        $npm = $ratios->firstWhere('ratio_name', 'Net Profit Margin (%)');
        if ($npm && $npm->actual > 0) {
            $this->bungaRatio = 1.5;
            $this->pajakRatio = 1.7;
            // Rekonstruksi margin
            $this->bebanUsahaRatio = 12.0;
            $this->gpm = round($npm->actual + $this->bebanUsahaRatio + $this->bungaRatio + $this->pajakRatio, 1);
        }

        $invRatio = $ratios->firstWhere('ratio_name', 'Inventory Turnover');
        if ($invRatio && $invRatio->actual > 0) {
            $calcDio = round(360 / $invRatio->actual, 0);
            $this->dioBahanBakuI1 = round($calcDio * 0.5, 0);
            $this->dioWipP6 = round($calcDio * 0.2, 0);
            $this->dioBarangJadiI2 = round($calcDio * 0.3, 0);
        }
    }

    public function resetBaseline()
    {
        $this->penjualan = 100.0;
        $this->gpm = 30.0;
        $this->bebanUsahaRatio = 12.0;
        $this->bungaRatio = 1.5;
        $this->pajakRatio = 1.7;

        $this->termKreditS5 = 30.0;
        $this->hariTerlambatC1 = 15.0;

        $this->dioBahanBakuI1 = 25.0;
        $this->dioWipP6 = 10.0;
        $this->dioBarangJadiI2 = 15.0;

        $this->termSupplierB2 = 45.0;
        $this->hariBayarCepat = 10.0;

        $this->kasIdle = 4.0;
        $this->asetTetap = 50.0;
        $this->utangBerbunga = 25.0;
        $this->kewajibanLancarLain = 5.0;
        session()->flash('message', 'Parameter DuPont berhasil dikembalikan ke baseline standar pabrik botol plastik.');
    }

    public function showDetailKode($kode)
    {
        if (isset($this->kpiDictionary[$kode])) {
            $this->selectedKode = $kode;
        }
    }

    public function closeDetailKode()
    {
        $this->selectedKode = null;
    }

    public function render()
    {
        // 1. Profitabilitas (NPM & Margin)
        $penjualan = max(0.1, (float)$this->penjualan);
        $gpm = (float)$this->gpm;
        $bebanUsahaRatio = (float)$this->bebanUsahaRatio;
        $bungaRatio = (float)$this->bungaRatio;
        $pajakRatio = (float)$this->pajakRatio;

        $hpp = $penjualan * (1 - ($gpm / 100));
        $labaKotor = $penjualan - $hpp;

        $ebitMargin = $gpm - $bebanUsahaRatio;
        $ebit = $penjualan * ($ebitMargin / 100);

        $bebanBunga = $penjualan * ($bungaRatio / 100);
        $bebanPajak = $penjualan * ($pajakRatio / 100);

        $npm = $ebitMargin - $bungaRatio - $pajakRatio;
        $labaBersih = $penjualan * ($npm / 100);

        // 2. Efisiensi Aset (ATO, Working Capital & Total Aset)
        $dso = (float)$this->termKreditS5 + (float)$this->hariTerlambatC1;
        $dio = (float)$this->dioBahanBakuI1 + (float)$this->dioWipP6 + (float)$this->dioBarangJadiI2;
        $dpo = max(0, (float)$this->termSupplierB2 - (float)$this->hariBayarCepat);

        $piutang = ($dso * $penjualan) / 360;
        $persediaan = ($dio * $hpp) / 360;
        $kas = max(0, (float)$this->kasIdle);
        $asetTetap = max(0, (float)$this->asetTetap);

        $totalAsetLancar = $kas + $piutang + $persediaan;
        $totalAset = $totalAsetLancar + $asetTetap;

        $ato = $totalAset > 0 ? ($penjualan / $totalAset) : 0;

        // 3. Leverage & Struktur Modal (1 + DER)
        $utangUsaha = ($dpo * $hpp) / 360;
        $kewajibanLancarLain = max(0, (float)$this->kewajibanLancarLain);
        $kewajibanLancar = $utangUsaha + $kewajibanLancarLain;

        $utangBerbunga = max(0, (float)$this->utangBerbunga);
        $totalLiabilitas = $kewajibanLancar + $utangBerbunga;

        // Ekuitas = Total Aset - Total Liabilitas (menjaga neraca seimbang)
        $ekuitas = max(0.1, $totalAset - $totalLiabilitas);

        $der = $ekuitas > 0 ? ($totalLiabilitas / $ekuitas) : 0;
        $leverageMultiplier = $ekuitas > 0 ? ($totalAset / $ekuitas) : 0; // = 1 + DER

        // 4. Apex ROE DuPont
        // ROE = NPM * ATO * (1 + DER)
        $roe = ($npm / 100) * $ato * $leverageMultiplier * 100;

        // 5. Identitas Penghubung
        $roa = ($ebitMargin / 100) * $ato * 100; // ROA = EBIT Margin * ATO
        $dta = $leverageMultiplier > 0 ? ($der / $leverageMultiplier) * 100 : 0; // DTA = DER / (1 + DER)
        $ccc = $dso + $dio - $dpo; // Cash Conversion Cycle

        $arTurnover = $dso > 0 ? (360 / $dso) : 0;
        $invTurnover = $dio > 0 ? (360 / $dio) : 0;

        $cr = $kewajibanLancar > 0 ? ($totalAsetLancar / $kewajibanLancar) : 0;
        $qr = $kewajibanLancar > 0 ? (($kas + $piutang) / $kewajibanLancar) : 0;

        $metrics = [
            'penjualan' => $penjualan,
            'hpp' => $hpp,
            'labaKotor' => $labaKotor,
            'ebit' => $ebit,
            'ebitMargin' => $ebitMargin,
            'bebanBunga' => $bebanBunga,
            'bebanPajak' => $bebanPajak,
            'npm' => $npm,
            'labaBersih' => $labaBersih,
            'dso' => $dso,
            'dio' => $dio,
            'dpo' => $dpo,
            'piutang' => $piutang,
            'persediaan' => $persediaan,
            'kas' => $kas,
            'asetTetap' => $asetTetap,
            'totalAsetLancar' => $totalAsetLancar,
            'totalAset' => $totalAset,
            'ato' => $ato,
            'utangUsaha' => $utangUsaha,
            'kewajibanLancar' => $kewajibanLancar,
            'utangBerbunga' => $utangBerbunga,
            'totalLiabilitas' => $totalLiabilitas,
            'ekuitas' => $ekuitas,
            'der' => $der,
            'leverageMultiplier' => $leverageMultiplier,
            'roe' => $roe,
            'roa' => $roa,
            'dta' => $dta,
            'ccc' => $ccc,
            'arTurnover' => $arTurnover,
            'invTurnover' => $invTurnover,
            'cr' => $cr,
            'qr' => $qr,
        ];

        return view('livewire.pohon-dupont', [
            'metrics' => $metrics,
            'periods' => Period::orderBy('period', 'desc')->get(),
            'viewMode' => $this->viewMode,
            'penjualan' => $this->penjualan,
            'gpm' => $this->gpm,
            'bebanUsahaRatio' => $this->bebanUsahaRatio,
            'bungaRatio' => $this->bungaRatio,
            'pajakRatio' => $this->pajakRatio,
            'termKreditS5' => $this->termKreditS5,
            'hariTerlambatC1' => $this->hariTerlambatC1,
            'dioBahanBakuI1' => $this->dioBahanBakuI1,
            'dioWipP6' => $this->dioWipP6,
            'dioBarangJadiI2' => $this->dioBarangJadiI2,
            'termSupplierB2' => $this->termSupplierB2,
            'hariBayarCepat' => $this->hariBayarCepat,
            'kasIdle' => $this->kasIdle,
            'asetTetap' => $this->asetTetap,
            'utangBerbunga' => $this->utangBerbunga,
            'kewajibanLancarLain' => $this->kewajibanLancarLain,
            'selectedKode' => $this->selectedKode,
            'kpiDictionary' => $this->kpiDictionary,
        ])->layout('layouts.app', ['title' => 'Pohon Nilai DuPont (ROE & Driver Indikator)']);
    }
}
