<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Period;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Auth;

class KonsensusIbp extends Component
{
    public $selectedPeriod = '2026-08';

    // 5 Pilar IBP Data & Status
    // 1. Product Review (NPD, R&D, Packaging)
    public $product_status = 'approved'; // draft, review, approved
    public $product_notes = 'Peluncuran botol HDPE 250ml varian baru disetujui. Uji kompresi dan drop test lolos spesifikasi pabrik.';
    public $product_pic = 'Kadep R&D / Produk';

    // 2. Demand Review (Sales & Marketing Forecast)
    public $demand_status = 'approved';
    public $demand_notes = 'Target volume penjualan 1.250.000 botol/bulan dengan proyeksi pertumbuhan 8.5% YoY dari sektor herbal & kosmetik.';
    public $demand_pic = 'Kadep Sales & Marketing';

    // 3. Supply Review (Plant Production, Raw Material Resin & Cap)
    public $supply_status = 'approved';
    public $supply_notes = 'Kapasitas lini blow-molding aman di 85% utilitas mesin. Pasokan resin virgin & regrind terjamin untuk 60 hari buffer.';
    public $supply_pic = 'Kadep Produksi & Pabrik';

    // 4. Financial Reconciliation (COGS, OPEX, Cash Flow)
    public $finance_status = 'approved';
    public $finance_notes = 'Gross Margin diproyeksikan stabil pada 28.5%. Cash flow operasional positif mendukung capex perawatan mold.';
    public $finance_pic = 'Kadep FAT / Keuangan';

    // 5. Management Business Review (MBR / Direksi Approval)
    public $mbr_status = 'approved';
    public $mbr_notes = 'Konsensus 5 pilar IBP disahkan oleh Direksi. Pagu anggaran dan target pendapatan 12 bulan ke depan menjadi acuan resmi RKAP.';
    public $mbr_pic = 'Direksi / Managing Director';

    // Consensus Summary & Sign-off
    public $signer_name = '';
    public $signer_role = 'Direktur Operasional & Keuangan';
    public $meeting_date = '2026-08-20';
    public $consensus_summary = 'Seluruh departemen menyepakati rencana terpadu supply-demand periode 2026-08. Tidak ada bottleneck material kritis.';

    public function mount()
    {
        $reqPeriod = request()->query('period') ?? session('active_period');
        if ($reqPeriod) {
            $this->selectedPeriod = $reqPeriod;
        }
        session(['active_period' => $this->selectedPeriod]);

        $this->signer_name = Auth::user() ? Auth::user()->name : 'Direktur Operasional';
        $this->loadConsensusData();
    }

    public function updatedSelectedPeriod($val)
    {
        session(['active_period' => $val]);
        $this->loadConsensusData();
    }

    public function loadConsensusData()
    {
        $data = AppSetting::getValue('ibp_consensus_' . $this->selectedPeriod);
        if ($data) {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                $this->product_status = $decoded['product_status'] ?? 'draft';
                $this->product_notes = $decoded['product_notes'] ?? '';
                $this->product_pic = $decoded['product_pic'] ?? 'Kadep R&D / Produk';

                $this->demand_status = $decoded['demand_status'] ?? 'draft';
                $this->demand_notes = $decoded['demand_notes'] ?? '';
                $this->demand_pic = $decoded['demand_pic'] ?? 'Kadep Sales & Marketing';

                $this->supply_status = $decoded['supply_status'] ?? 'draft';
                $this->supply_notes = $decoded['supply_notes'] ?? '';
                $this->supply_pic = $decoded['supply_pic'] ?? 'Kadep Produksi & Pabrik';

                $this->finance_status = $decoded['finance_status'] ?? 'draft';
                $this->finance_notes = $decoded['finance_notes'] ?? '';
                $this->finance_pic = $decoded['finance_pic'] ?? 'Kadep FAT / Keuangan';

                $this->mbr_status = $decoded['mbr_status'] ?? 'draft';
                $this->mbr_notes = $decoded['mbr_notes'] ?? '';
                $this->mbr_pic = $decoded['mbr_pic'] ?? 'Direksi / Managing Director';

                $this->signer_name = $decoded['signer_name'] ?? (Auth::user() ? Auth::user()->name : 'Direktur Operasional');
                $this->signer_role = $decoded['signer_role'] ?? 'Direktur Operasional & Keuangan';
                $this->meeting_date = $decoded['meeting_date'] ?? date('Y-m-d');
                $this->consensus_summary = $decoded['consensus_summary'] ?? '';
            }
        }
    }

    public function saveConsensus()
    {
        $payload = [
            'period' => $this->selectedPeriod,
            'product_status' => $this->product_status,
            'product_notes' => $this->product_notes,
            'product_pic' => $this->product_pic,
            'demand_status' => $this->demand_status,
            'demand_notes' => $this->demand_notes,
            'demand_pic' => $this->demand_pic,
            'supply_status' => $this->supply_status,
            'supply_notes' => $this->supply_notes,
            'supply_pic' => $this->supply_pic,
            'finance_status' => $this->finance_status,
            'finance_notes' => $this->finance_notes,
            'finance_pic' => $this->finance_pic,
            'mbr_status' => $this->mbr_status,
            'mbr_notes' => $this->mbr_notes,
            'mbr_pic' => $this->mbr_pic,
            'signer_name' => $this->signer_name,
            'signer_role' => $this->signer_role,
            'meeting_date' => $this->meeting_date,
            'consensus_summary' => $this->consensus_summary,
            'updated_at' => now()->toDateTimeString(),
            'updated_by' => Auth::user() ? Auth::user()->name : 'User',
        ];

        AppSetting::setValue('ibp_consensus_' . $this->selectedPeriod, json_encode($payload));
        session()->flash('message', "Hasil Konsensus Rapat IBP untuk Periode {$this->selectedPeriod} berhasil disimpan.");
    }

    public function approveAllSteps()
    {
        $this->product_status = 'approved';
        $this->demand_status = 'approved';
        $this->supply_status = 'approved';
        $this->finance_status = 'approved';
        $this->mbr_status = 'approved';
        $this->saveConsensus();
    }

    public function render()
    {
        $periods = Period::pluck('period')->toArray();
        if (empty($periods)) {
            $periods = ['2026-08', '2026-07', '2026-06'];
        }

        // Hitung persentase kesiapan IBP
        $steps = [$this->product_status, $this->demand_status, $this->supply_status, $this->finance_status, $this->mbr_status];
        $approvedCount = count(array_filter($steps, fn($s) => $s === 'approved'));
        $reviewCount = count(array_filter($steps, fn($s) => $s === 'review'));
        $readinessPct = round(($approvedCount * 100) / 5, 0);

        return view('livewire.konsensus-ibp', [
            'periods' => $periods,
            'approvedCount' => $approvedCount,
            'reviewCount' => $reviewCount,
            'readinessPct' => $readinessPct,
        ])->layout('layouts.app', ['title' => 'Konsensus Rapat IBP']);
    }
}
