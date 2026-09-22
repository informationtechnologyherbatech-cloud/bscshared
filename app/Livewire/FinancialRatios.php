<?php

namespace App\Livewire;

use App\Livewire\Concerns\FollowsActivePeriod;
use App\Models\FinancialRatio;
use App\Models\Period;
use Livewire\Attributes\Url;
use Livewire\Component;

class FinancialRatios extends Component
{
    use FollowsActivePeriod;

    public $selectedCategory = '';

    #[Url(as: 'status')]
    public $selectedStatus = '';

    #[Url(as: 'period')]
    public $selectedPeriod = '';

    public $editingRatioId = null;
    public $editTarget = 0;
    public $editActual = 0;

    public function mount()
    {
        if (request()->query('status') && request()->query('status') !== 'all') {
            $this->selectedStatus = request()->query('status');
        }
        if ($this->selectedStatus === 'all') {
            $this->selectedStatus = ''; // "Semua" dari dashboard = tanpa saringan
        }
        // Bawaan: periode aktif di navbar; ?period= dari tautan halaman lain menggantikannya.
        $this->selectedPeriod = $this->initialPeriod(request()->query('period') ?: $this->selectedPeriod);
    }

    public function updatedSelectedPeriod(): void
    {
        $this->shareActivePeriod((string) $this->selectedPeriod);
    }

    public function editRatio($id)
    {
        if (!auth()->user()?->can('manage ratios')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang mengelola rasio (butuh manage ratios / Admin FAT / Super Admin).');
            return;
        }
        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat diubah.');
            return;
        }

        $ratio = FinancialRatio::findOrFail($id);
        if ($ratio->isComputed()) {
            session()->flash('error', 'Rasio '.$ratio->ratio_name.' dihitung otomatis dari pos akun. Ubah lewat menu Pos Akun atau Katalog Rasio.');
            return;
        }
        $this->editingRatioId = $ratio->id;
        $this->editTarget = $ratio->target;
        $this->editActual = $ratio->actual;
    }

    public function updateRatio()
    {
        if (!$this->editingRatioId) return;
        if (!auth()->user()?->can('manage ratios')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang mengelola rasio.');
            $this->editingRatioId = null;
            return;
        }

        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat diubah.');
            $this->editingRatioId = null;
            return;
        }

        $ratio = FinancialRatio::findOrFail($this->editingRatioId);
        if ($ratio->isComputed()) {
            session()->flash('error', 'Rasio hasil hitungan pos akun tidak dapat diubah langsung.');
            $this->editingRatioId = null;
            return;
        }
        $target = floatval($this->editTarget);
        $actual = floatval($this->editActual);

        // Simple percentage calculation
        $ach = $target > 0 ? round(($actual / $target) * 100, 2) : 100;
        if ($ach > 100) $ach = 100.00;
        
        $status = 'Waspada';
        if ($ach >= 100) {
            $status = 'Tercapai';
        } elseif ($ach < 80) {
            $status = 'Di Bawah Target';
        }

        $ratio->update([
            'target' => $target,
            'actual' => $actual,
            'achievement_pct' => $ach,
            'status' => $status,
        ]);

        $this->editingRatioId = null;
        session()->flash('message', 'Nilai rasio ' . $ratio->ratio_name . ' berhasil diperbarui!');
    }

    public function cancelEdit()
    {
        $this->editingRatioId = null;
    }

    public function render()
    {
        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        $isClosed = $periodObj ? $periodObj->isClosed() : false;

        $query = FinancialRatio::where('period', $this->selectedPeriod);
        if ($this->selectedCategory) {
            $query->where('category', $this->selectedCategory);
        }
        if ($this->selectedStatus) {
            if ($this->selectedStatus === 'bermasalah') {
                $query->whereIn('status', ['Waspada', 'Di Bawah Target', 'Off-Target']);
            } elseif ($this->selectedStatus === 'Di Bawah Target') {
                $query->whereIn('status', ['Di Bawah Target', 'Off-Target']);
            } elseif ($this->selectedStatus !== 'all') {
                $query->where('status', $this->selectedStatus);
            }
        }
        $ratios = $query->get();

        $categories = FinancialRatio::distinct()->pluck('category')->toArray();
        $periods = Period::list();

        return view('livewire.financial-ratios', [
            'ratios' => $ratios,
            'categories' => $categories,
            'periods' => $periods,
            'isClosed' => $isClosed,
        ])->layout('layouts.app', ['title' => 'Rasio Keuangan']);
    }
}
