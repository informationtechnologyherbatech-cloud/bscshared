<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\Period;
use App\Models\FinancialRatio;
use App\Models\AppSetting;

class UjiDampakSandbox extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    // 5 Strategic Driver Sliders (% change or shift)
    public $salesVolumeGrowth = 0.0;    // -30% s/d +30%
    public $cogsChange = 0.0;           // -20% s/d +20% (kenaikan/penurunan harga bahan baku herbal)
    public $opexChange = 0.0;           // -20% s/d +20% (efisiensi beban operasional)
    public $arCollectionDaysShift = 0;  // -30 hari s/d +30 hari (perubahan DSO/penagihan)
    public $apPaymentDaysShift = 0;     // -30 hari s/d +30 hari (perubahan DPO/pembayaran supplier)

    // Preset active name
    public $activePreset = 'custom';

    public function mount()
    {
        $reqPeriod = request()->query('period') ?? session('active_period');
        if ($reqPeriod) {
            $this->selectedPeriod = $reqPeriod;
        }
        session(['active_period' => $this->selectedPeriod]);
        $this->resetToBaseline();
    }

    public function updatedSelectedPeriod($val)
    {
        session(['active_period' => $val]);
        $this->resetToBaseline();
    }

    public function resetToBaseline()
    {
        $this->salesVolumeGrowth = 0.0;
        $this->cogsChange = 0.0;
        $this->opexChange = 0.0;
        $this->arCollectionDaysShift = 0;
        $this->apPaymentDaysShift = 0;
        $this->activePreset = 'baseline';
    }

    public function applyPreset($preset)
    {
        $this->activePreset = $preset;
        if ($preset === 'optimis') {
            $this->salesVolumeGrowth = 15.0;
            $this->cogsChange = -3.0;
            $this->opexChange = -5.0;
            $this->arCollectionDaysShift = -10;
            $this->apPaymentDaysShift = 10;
        } elseif ($preset === 'moderat') {
            $this->salesVolumeGrowth = 5.0;
            $this->cogsChange = 2.0;
            $this->opexChange = 0.0;
            $this->arCollectionDaysShift = -5;
            $this->apPaymentDaysShift = 5;
        } elseif ($preset === 'krisis') {
            $this->salesVolumeGrowth = -10.0;
            $this->cogsChange = 8.0;
            $this->opexChange = 4.0;
            $this->arCollectionDaysShift = 15;
            $this->apPaymentDaysShift = -15;
        } elseif ($preset === 'baseline') {
            $this->resetToBaseline();
        }
    }

    public function render()
    {
        $periods = Period::pluck('period')->toArray();
        $periodObj = Period::where('period', $this->selectedPeriod)->first();

        // 1. Financial Baseline Values (Dalam Miliar IDR)
        $baseRevenue = (float) ($periodObj ? $periodObj->actual_revenue : 115.20);
        $baseTargetRevenue = (float) ($periodObj ? $periodObj->target_revenue : 120.00);

        // HPP baseline Herbatech (GPM riil ~ 75.12%, sehingga HPP ~ 24.88% dari revenue)
        $baseCOGS = $baseRevenue * 0.2488;
        $baseGrossProfit = $baseRevenue - $baseCOGS;

        // OPEX baseline (EBIT margin ~ 98.86% terhadap GP/Rev ratio riil, beban operasional ~ 1.16% dari sales)
        $baseOPEX = $baseRevenue * 0.0116;
        $baseEBIT = $baseGrossProfit - $baseOPEX;

        // Net Profit baseline (NPM ~ 73.97%)
        $baseNetProfit = $baseRevenue * 0.7397;

        // Baseline Neraca Sederhana (Untuk Rasio)
        $baseTotalAsset = $baseRevenue / 0.41; // TATO 0.41x -> Asset ~ 280.97 M
        $baseEquity = $baseNetProfit / (0.3362); // ROE 33.62% -> Equity ~ 253.4 M
        $baseLiabilities = $baseEquity * 0.12; // DER 0.12x -> Liab ~ 30.4 M
        $baseCurrentLiabilities = $baseLiabilities * 0.85; // ~ 25.8 M
        $baseCurrentAssets = $baseCurrentLiabilities * 8.20; // CR 8.20x -> CA ~ 211.5 M

        // 2. SIMULATED VALUES (Hasil tuas slider)
        $simSalesFactor = 1 + ($this->salesVolumeGrowth / 100);
        $simCOGSFactor = 1 + ($this->cogsChange / 100);
        $simOPEXFactor = 1 + ($this->opexChange / 100);

        $simRevenue = $baseRevenue * $simSalesFactor;
        $simCOGS = $baseCOGS * $simSalesFactor * $simCOGSFactor;
        $simGrossProfit = $simRevenue - $simCOGS;
        $simOPEX = $baseOPEX * $simOPEXFactor;
        $simEBIT = $simGrossProfit - $simOPEX;
        
        // Sim Net Profit (dengan asumsi tarif pajak & bunga proporsional)
        $taxInterestRate = $baseEBIT > 0 ? ($baseEBIT - $baseNetProfit) / $baseEBIT : 0.15;
        $simNetProfit = max(0, $simEBIT * (1 - $taxInterestRate));

        // Proyeksi Arus Kas Bersih Dampak (Operating Cash Flow Shift)
        $arCollectionImpact = ($baseRevenue / 365) * (-$this->arCollectionDaysShift); // percepat hari = tambah kas
        $apPaymentImpact = ($simCOGS / 365) * ($this->apPaymentDaysShift); // perlambat hari = hemat kas
        $simCashDelta = ($simNetProfit - $baseNetProfit) + $arCollectionImpact + $apPaymentImpact;

        // 3. MATRIKS 15 RASIO KEUANGAN KOMPARATIF
        $dbRatios = FinancialRatio::where('period', $this->selectedPeriod)->get();

        $ratiosComparison = [];
        foreach ($dbRatios as $r) {
            $name = strtoupper(trim($r->ratio_name));
            $baseVal = (float) $r->actual;
            $targetVal = (float) $r->target;
            $simVal = $baseVal;

            // Logika transmisi dampak ke masing-masing rasio
            if (str_contains($name, 'GROSS PROFIT MARGIN') || str_contains($name, 'GPM')) {
                $simVal = $simRevenue > 0 ? ($simGrossProfit / $simRevenue) * 100 : $baseVal;
            } elseif (str_contains($name, 'NET PROFIT MARGIN') || str_contains($name, 'NPM')) {
                $simVal = $simRevenue > 0 ? ($simNetProfit / $simRevenue) * 100 : $baseVal;
            } elseif (str_contains($name, 'EBIT')) {
                $simVal = $simRevenue > 0 ? ($simEBIT / $simRevenue) * 100 : $baseVal;
            } elseif (str_contains($name, 'RETURN ON EQUITY') || str_contains($name, 'ROE')) {
                $simEquity = $baseEquity + ($simNetProfit - $baseNetProfit);
                $simVal = $simEquity > 0 ? ($simNetProfit / $simEquity) * 100 : $baseVal;
            } elseif (str_contains($name, 'RETURN ON ASSET') || str_contains($name, 'ROA')) {
                $simAsset = $baseTotalAsset + ($simNetProfit - $baseNetProfit);
                $simVal = $simAsset > 0 ? ($simNetProfit / $simAsset) * 100 : $baseVal;
            } elseif (str_contains($name, 'TOTAL ASSET TURN OVER') || str_contains($name, 'TATO')) {
                $simAsset = $baseTotalAsset + ($simNetProfit - $baseNetProfit);
                $simVal = $simAsset > 0 ? ($simRevenue / $simAsset) : $baseVal;
            } elseif (str_contains($name, 'DAYS SALES OUTSTANDING') || str_contains($name, 'DSO')) {
                $simVal = max(10, $baseVal + $this->arCollectionDaysShift);
            } elseif (str_contains($name, 'DAYS PAYABLE OUTSTANDING') || str_contains($name, 'DPO')) {
                $simVal = max(10, $baseVal + $this->apPaymentDaysShift);
            } elseif (str_contains($name, 'INVENTORY TURN OVER') || str_contains($name, 'ITO')) {
                $simVal = $simCOGSFactor > 0 ? $baseVal * ($simSalesFactor / $simCOGSFactor) : $baseVal;
            } elseif (str_contains($name, 'CURRENT RATIO')) {
                $simCA = $baseCurrentAssets + $simCashDelta;
                $simVal = $baseCurrentLiabilities > 0 ? ($simCA / $baseCurrentLiabilities) : $baseVal;
            } elseif (str_contains($name, 'QUICK RATIO')) {
                $simQA = ($baseCurrentAssets * 0.70) + $simCashDelta;
                $simVal = $baseCurrentLiabilities > 0 ? ($simQA / $baseCurrentLiabilities) : $baseVal;
            } elseif (str_contains($name, 'DEBT TO EQUITY RATIO') || str_contains($name, 'DER')) {
                $simEquity = $baseEquity + ($simNetProfit - $baseNetProfit);
                $simVal = $simEquity > 0 ? ($baseLiabilities / $simEquity) : $baseVal;
            } elseif (str_contains($name, 'DEBT TO TOTAL ASSET') || str_contains($name, 'DAR')) {
                $simAsset = $baseTotalAsset + ($simNetProfit - $baseNetProfit);
                $simVal = $simAsset > 0 ? ($baseLiabilities / $simAsset) : $baseVal;
            }

            // Hitung capaian simulasi berdasarkan polaritas rasio
            $isLowerBetter = in_array($r->ratio_name, [
                'DEBT TO EQUITY RATIO (DER)',
                'DEBT TO TOTAL ASSET RATIO (DAR)',
                'DAYS SALES OUTSTANDING (DSO)',
                'DAYS INVENTORY OUTSTANDING (DIO)'
            ]);

            if ($r->ratio_name === 'DAYS PAYABLE OUTSTANDING (DPO)') {
                $simAch = ($simVal >= 60.0 && $simVal <= 110.0) ? 100.0 : 85.0;
            } elseif ($isLowerBetter) {
                if ($simVal <= $targetVal) {
                    $simAch = 100.0;
                } else {
                    $simAch = $simVal > 0 ? round(($targetVal / $simVal) * 100, 2) : 100.0;
                }
            } else {
                $simAch = $targetVal > 0 ? round(($simVal / $targetVal) * 100, 2) : 100.0;
                if ($simAch > 100) $simAch = 100.0;
            }

            $diff = $simVal - $baseVal;
            $diffDirection = 'equal';
            if ($isLowerBetter) {
                $diffDirection = $diff < 0 ? 'better' : ($diff > 0 ? 'worse' : 'equal');
            } else {
                $diffDirection = $diff > 0 ? 'better' : ($diff < 0 ? 'worse' : 'equal');
            }

            $ratiosComparison[] = [
                'name' => $r->ratio_name,
                'category' => $r->category,
                'unit' => $r->unit,
                'target' => $targetVal,
                'baseline' => $baseVal,
                'simulated' => round($simVal, 2),
                'diff' => round($diff, 2),
                'diff_direction' => $diffDirection,
                'sim_achievement' => $simAch,
                'sim_status' => $simAch >= 100 ? 'Tercapai' : ($simAch >= 80 ? 'Waspada' : 'Di Bawah Target'),
            ];
        }

        // 4. Hitung Proyeksi Skor Apex Tingkat 1
        $weightRev = (float) ($periodObj ? $periodObj->weight_revenue : 45.0);
        $weightRat = (float) ($periodObj ? $periodObj->weight_ratios : 55.0);
        
        $baseRevAch = $baseTargetRevenue > 0 ? min(100, ($baseRevenue / $baseTargetRevenue) * 100) : 100;
        $simRevAch = $baseTargetRevenue > 0 ? min(100, ($simRevenue / $baseTargetRevenue) * 100) : 100;

        $baseAvgRatioAch = count($ratiosComparison) > 0 ? collect($dbRatios)->avg('achievement_pct') : 95.0;
        $simAvgRatioAch = count($ratiosComparison) > 0 ? collect($ratiosComparison)->avg('sim_achievement') : 95.0;

        $baseApexScore = round(($baseRevAch * ($weightRev / 100)) + ($baseAvgRatioAch * ($weightRat / 100)), 2);
        $simApexScore = round(($simRevAch * ($weightRev / 100)) + ($simAvgRatioAch * ($weightRat / 100)), 2);
        $apexDelta = round($simApexScore - $baseApexScore, 2);

        return view('livewire.uji-dampak-sandbox', [
            'periods' => $periods,
            'baseRevenue' => $baseRevenue,
            'baseTargetRevenue' => $baseTargetRevenue,
            'baseCOGS' => $baseCOGS,
            'baseGrossProfit' => $baseGrossProfit,
            'baseOPEX' => $baseOPEX,
            'baseNetProfit' => $baseNetProfit,
            'simRevenue' => $simRevenue,
            'simCOGS' => $simCOGS,
            'simGrossProfit' => $simGrossProfit,
            'simOPEX' => $simOPEX,
            'simEBIT' => $simEBIT,
            'simNetProfit' => $simNetProfit,
            'simCashDelta' => $simCashDelta,
            'ratiosComparison' => $ratiosComparison,
            'baseApexScore' => $baseApexScore,
            'simApexScore' => $simApexScore,
            'apexDelta' => $apexDelta,
        ])->layout('layouts.app', ['title' => 'Uji Dampak / What-If Sandbox']);
    }
}
