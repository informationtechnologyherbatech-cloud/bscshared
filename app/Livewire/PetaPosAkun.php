<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\Period;
use App\Models\DepartmentObjective;
use Illuminate\Support\Facades\DB;

class PetaPosAkun extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    // Data Matriks: matrix[dept_code][pa_code] = 'K' | 'L' | '-'
    public $matrix = [];

    // 12 Pos Finansial Inti Pabrik Manufaktur Botol Plastik (Dipetakan ke 130 Akun & Chart of Accounts Odoo ERP)
    public $posAkunList = [
        [
            'code' => 'PA01',
            'name' => 'PENJUALAN BOTOL',
            'full_name' => 'Pendapatan Penjualan Botol Plastik (Jar, Galon, Botol PET/HDPE)',
            'ratio_link' => 'Revenue & Growth',
            'coa_range' => '4100xxxx (Revenue)',
            'odoo_accounts' => '41000010 - Penjualan Botol PET, 41000020 - Penjualan Galon & Jar',
        ],
        [
            'code' => 'PA02',
            'name' => 'BAHAN BAKU RESIN',
            'full_name' => 'Beban Pokok Biji Plastik PET/HDPE, Masterbatch, & Bahan Crusher',
            'ratio_link' => 'GPM & COGS',
            'coa_range' => '5100xxxx (COGS Material)',
            'odoo_accounts' => '51000010 - COGS Raw Material Biji Plastik, 51000020 - Masterbatch Pigmen',
        ],
        [
            'code' => 'PA03',
            'name' => 'LISTRIK & ENERGI',
            'full_name' => 'Biaya Listrik Pabrik & Utilitas Mesin Blow/Injection Moulding',
            'ratio_link' => 'GPM & Operasional',
            'coa_range' => '5100xxxx / 6110xxxx (Energy)',
            'odoo_accounts' => '61100310 - Beban Listrik PLN Pabrik & Gardu Trafo',
        ],
        [
            'code' => 'PA04',
            'name' => 'TENAGA KERJA',
            'full_name' => 'Biaya Gaji & Tunjangan Operator Mesin, Sortir, dan Packing',
            'ratio_link' => 'COGS & Produktivitas',
            'coa_range' => '5100xxxx / 6110xxxx (Payroll)',
            'odoo_accounts' => '61100110 - Gaji Operator & Lembur, 61100190 - BPJS Ketenagakerjaan',
        ],
        [
            'code' => 'PA05',
            'name' => 'MAINTENANCE MOULD',
            'full_name' => 'Biaya Perawatan Mesin Pabrik, Oli Hidrolik, Sparepart, & Moulding',
            'ratio_link' => 'TATO & Efisiensi Aset',
            'coa_range' => '5100xxxx / 6110xxxx (Maintenance)',
            'odoo_accounts' => '51008010 - Penyusutan Mesin, 61100230 - Perawatan Moulding & Mesin',
        ],
        [
            'code' => 'PA06',
            'name' => 'PERSEDIAAN BOTOL',
            'full_name' => 'Persediaan Bahan Baku Biji Plastik & Stok Botol Jadi di Gudang',
            'ratio_link' => 'ITO & DIO',
            'coa_range' => '1130xxxx (Asset Inventory)',
            'odoo_accounts' => '11310010 - Persediaan Bahan Baku Resin, 11330010 - Persediaan Barang Jadi Botol',
        ],
        [
            'code' => 'PA07',
            'name' => 'PIUTANG USAHA',
            'full_name' => 'Piutang Penjualan Pelanggan, Distributor, dan Maklon Botol',
            'ratio_link' => 'DSO & AR Turnover',
            'coa_range' => '1120xxxx (Asset Receivable)',
            'odoo_accounts' => '11210010 - Piutang Usaha Pelanggan, 11210020 - Piutang Karyawan',
        ],
        [
            'code' => 'PA08',
            'name' => 'UTANG SUPPLIER',
            'full_name' => 'Utang Usaha Supplier Biji Plastik & Vendor Bahan Kemas',
            'ratio_link' => 'DPO & Cash Flow',
            'coa_range' => '2110xxxx (Liability Payable)',
            'odoo_accounts' => '21110010 - Utang Usaha Supplier Resin & Vendor',
        ],
        [
            'code' => 'PA09',
            'name' => 'KAS & REKENING',
            'full_name' => 'Kas Operasional & Rekening Giro Bank untuk Likuiditas',
            'ratio_link' => 'Current & Quick Ratio',
            'coa_range' => '1110xxxx (Cash & Bank)',
            'odoo_accounts' => '11110010 - Petty Cash, 11110001 - BSI, 11120013 - Mandiri, 11120014 - BCA',
        ],
        [
            'code' => 'PA10',
            'name' => 'BIAYA PENGIRIMAN',
            'full_name' => 'Beban Ekspedisi & Armada Truk Pengiriman Botol ke Customer',
            'ratio_link' => 'OPEX & Logistik',
            'coa_range' => '6110xxxx (Delivery OPEX)',
            'odoo_accounts' => '61100510 - Beban Ekspedisi & Pengiriman Armada',
        ],
        [
            'code' => 'PA11',
            'name' => 'MESIN & BANGUNAN',
            'full_name' => 'Aset Tetap Pabrik, Mesin Injection/Blow, & Bangunan Pabrik',
            'ratio_link' => 'ROA & TATO',
            'coa_range' => '1210xxxx (Fixed Asset)',
            'odoo_accounts' => '12110010 - Mesin Pabrik, 12110020 - Bangunan & Moulding Cetak',
        ],
        [
            'code' => 'PA12',
            'name' => 'BEBAN UMUM (GA)',
            'full_name' => 'Beban Administrasi, Legalitas, IT, Keamanan, & Umum',
            'ratio_link' => 'NPM & EBIT Margin',
            'coa_range' => '6110xxxx (General Admin)',
            'odoo_accounts' => '61100410 - Beban Kantor & Legalitas, 61100330 - Pajak & Retribusi',
        ],
    ];

    public function mount()
    {
        $reqPeriod = request()->query('period') ?? session('active_period');
        if ($reqPeriod) {
            $this->selectedPeriod = $reqPeriod;
        }
        session(['active_period' => $this->selectedPeriod]);

        $this->loadMatrix();
    }

    public function updatedSelectedPeriod($val)
    {
        session(['active_period' => $val]);
        $this->loadMatrix();
    }

    /**
     * Muat matriks dari tabel app_settings (JSON) atau inisialisasi default
     */
    public function loadMatrix()
    {
        $key = 'peta_pos_matrix_12_' . $this->selectedPeriod;
        $saved = DB::table('app_settings')->where('key', $key)->value('value');

        if ($saved) {
            $this->matrix = json_decode($saved, true) ?: [];
        } else {
            $this->matrix = $this->getDefaultMatrix();
        }
    }

    /**
     * Matriks Baku Hubungan 10 Departemen Odoo x 12 Pos Finansial Pabrik Botol Plastik
     */
    private function getDefaultMatrix()
    {
        return [
            'PRD' => [ // PRODUKSI (Pabrik Moulding/Blow)
                'PA01' => '-', 'PA02' => 'K', 'PA03' => 'K', 'PA04' => 'K', 'PA05' => 'K',
                'PA06' => 'K', 'PA07' => '-', 'PA08' => '-', 'PA09' => '-', 'PA10' => '-',
                'PA11' => 'K', 'PA12' => '-'
            ],
            'ENG' => [ // ENGINEERING & MAINTENANCE
                'PA01' => '-', 'PA02' => 'L', 'PA03' => 'K', 'PA04' => 'L', 'PA05' => 'K',
                'PA06' => 'L', 'PA07' => '-', 'PA08' => '-', 'PA09' => '-', 'PA10' => '-',
                'PA11' => 'K', 'PA12' => '-'
            ],
            'PCH&IT' => [ // PURCHASING & IT
                'PA01' => '-', 'PA02' => 'K', 'PA03' => 'L', 'PA04' => '-', 'PA05' => 'K',
                'PA06' => 'K', 'PA07' => '-', 'PA08' => 'K', 'PA09' => 'L', 'PA10' => 'L',
                'PA11' => 'L', 'PA12' => 'K'
            ],
            'WH' => [ // PPIC & WAREHOUSE
                'PA01' => 'L', 'PA02' => 'L', 'PA03' => '-', 'PA04' => 'L', 'PA05' => '-',
                'PA06' => 'K', 'PA07' => '-', 'PA08' => '-', 'PA09' => '-', 'PA10' => 'K',
                'PA11' => 'L', 'PA12' => '-'
            ],
            'QA & QC' => [ // QA & QC
                'PA01' => 'L', 'PA02' => 'K', 'PA03' => 'L', 'PA04' => 'L', 'PA05' => 'L',
                'PA06' => 'K', 'PA07' => '-', 'PA08' => '-', 'PA09' => '-', 'PA10' => 'L',
                'PA11' => 'L', 'PA12' => '-'
            ],
            'SM' => [ // SALES & MARKETING
                'PA01' => 'K', 'PA02' => 'L', 'PA03' => '-', 'PA04' => '-', 'PA05' => '-',
                'PA06' => 'L', 'PA07' => 'K', 'PA08' => '-', 'PA09' => 'K', 'PA10' => 'K',
                'PA11' => '-', 'PA12' => 'L'
            ],
            'ACCTAX' => [ // ACCOUNTING & TAX
                'PA01' => 'L', 'PA02' => 'K', 'PA03' => 'K', 'PA04' => 'K', 'PA05' => 'K',
                'PA06' => 'K', 'PA07' => 'K', 'PA08' => 'K', 'PA09' => 'K', 'PA10' => 'L',
                'PA11' => 'K', 'PA12' => 'K'
            ],
            'FIN' => [ // FINANCE
                'PA01' => 'L', 'PA02' => 'L', 'PA03' => 'L', 'PA04' => 'L', 'PA05' => 'L',
                'PA06' => '-', 'PA07' => 'K', 'PA08' => 'K', 'PA09' => 'K', 'PA10' => 'L',
                'PA11' => 'L', 'PA12' => 'L'
            ],
            'HRGA' => [ // HRD & GA
                'PA01' => '-', 'PA02' => '-', 'PA03' => 'L', 'PA04' => 'K', 'PA05' => '-',
                'PA06' => '-', 'PA07' => '-', 'PA08' => '-', 'PA09' => 'L', 'PA10' => 'L',
                'PA11' => '-', 'PA12' => 'K'
            ],
            'MR' => [ // MANAGEMENT REPRESENTATIVE
                'PA01' => 'L', 'PA02' => 'L', 'PA03' => 'L', 'PA04' => 'L', 'PA05' => 'L',
                'PA06' => 'L', 'PA07' => '-', 'PA08' => '-', 'PA09' => '-', 'PA10' => '-',
                'PA11' => 'L', 'PA12' => 'L'
            ],
        ];
    }

    /**
     * Ubah status sel matriks (Kuat, Lemah, Netral)
     */
    public function updateCell($deptCode, $paCode, $val)
    {
        $this->matrix[$deptCode][$paCode] = $val;

        // Simpan ke database app_settings secara instan
        $key = 'peta_pos_matrix_12_' . $this->selectedPeriod;
        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($this->matrix), 'updated_at' => now()]
        );
    }

    /**
     * Reset matriks ke konfigurasi awal
     */
    public function resetMatrix()
    {
        $this->matrix = $this->getDefaultMatrix();
        $key = 'peta_pos_matrix_12_' . $this->selectedPeriod;
        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($this->matrix), 'updated_at' => now()]
        );
    }

    public function render()
    {
        $periods = Period::pluck('period')->toArray();

        // 1. Ambil 10 Departemen Asli dari database Odoo ERP (mysql_finance)
        $departments = [];
        try {
            $deptRows = DB::connection('mysql_finance')
                ->table('departments')
                ->select('id', 'name', 'code')
                ->orderBy('id', 'asc')
                ->get();
            $departments = $deptRows->toArray();
        } catch (\Exception $e) {
            $departments = DB::table('departments')->where('is_active', 1)->orderBy('sort_order')->get()->toArray();
        }

        $totalDepts = count($departments);
        $totalPos = count($this->posAkunList);

        // Hitung akumulasi Kuat (K) dan Lemah (L)
        $countK = 0;
        $countL = 0;
        foreach ($this->matrix as $dCode => $row) {
            foreach ($row as $paCode => $val) {
                if ($val === 'K') $countK++;
                if ($val === 'L') $countL++;
            }
        }

        // Ambil sasaran mutu terkait per departemen untuk popover / tooltip
        $deptObjectives = DepartmentObjective::where('period', $this->selectedPeriod)->get()->groupBy('dept_code');

        return view('livewire.peta-pos-akun', [
            'periods' => $periods,
            'departments' => $departments,
            'posAkunList' => $this->posAkunList,
            'totalDepts' => $totalDepts,
            'totalPos' => $totalPos,
            'countK' => $countK,
            'countL' => $countL,
            'deptObjectives' => $deptObjectives,
        ])->layout('layouts.app', ['title' => 'Peta Pos Akun — Matriks Hubungan Pos Akun & Unit Kerja']);
    }
}
