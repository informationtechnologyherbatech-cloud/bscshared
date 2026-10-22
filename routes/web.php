<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Auth\Login;
use App\Livewire\BscDashboard;
use App\Livewire\ApexConsolidation;
use App\Livewire\FinancialRatios;
use App\Livewire\DepartmentObjectives;
use App\Livewire\ActionPlans;
use App\Livewire\StagingLogs;
use App\Livewire\SystemIntegration;
use App\Livewire\BscWiring;
use App\Livewire\ManageUsers;
use App\Livewire\AppSettings;
use App\Livewire\MasterDepartments;
use App\Livewire\UjiDampakSandbox;
use App\Livewire\SimulasiCoa;
use App\Livewire\PetaPosAkun;
use App\Livewire\UjiIndikatorDepartemen;
use App\Livewire\PohonDupont;

// Guest: Login 
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

// Authenticated: Logout 
Route::post('/logout', function (\Illuminate\Http\Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login')->with('status', 'Anda telah berhasil logout.');
})->middleware('auth')->name('logout');

// Protected App Routes — 13 Menu PRD §4 Tabel 4 (permission enforced server-side §9)
Route::middleware(['auth', 'active'])->group(function () {
    // 1 Piramida — view dashboard (semua peran punya)
    Route::get('/', BscDashboard::class)->middleware('permission:view dashboard')->name('dashboard');
    // Tingkat 1 Apex & Konsolidasi Hop 4
    Route::get('/apex-consolidation', ApexConsolidation::class)->middleware('permission:view dashboard|view ratios')->name('apex-consolidation');
    // Pohon Nilai DuPont & Driver Indikator Operasional
    Route::get('/pohon-dupont', PohonDupont::class)->middleware('permission:view dashboard|view ratios')->name('pohon-dupont');
    // 2 Rasio — view ratios (read), manage ratios (write)
    Route::get('/ratios', FinancialRatios::class)->middleware('permission:view ratios|view dashboard')->name('financial-ratios');
    // 3 Objective — view objectives
    Route::get('/objectives', DepartmentObjectives::class)->middleware('permission:view objectives|view dashboard')->name('department-objectives');
    // 4 Wiring — view wiring
    Route::get('/wiring', BscWiring::class)->middleware('permission:view wiring')->name('bsc-wiring');
    // 5 Dampak / What-If Sandbox — view dampak (Super Admin, FAT, Kadep)
    Route::get('/dampak', UjiDampakSandbox::class)->middleware('permission:view dampak')->name('dampak');
    // 6 Simulasi CoA — view coa (Super Admin, FAT)
    Route::get('/coa', SimulasiCoa::class)->middleware('permission:view coa')->name('coa');
    // Peta Pos Akun & Alokasi Anggaran Departemen
    Route::get('/peta-pos-akun', PetaPosAkun::class)->middleware('permission:view coa|view dashboard')->name('peta-pos-akun');
    // Uji Indikator Departemen Level 3
    Route::get('/uji-indikator', UjiIndikatorDepartemen::class)->middleware('permission:view objectives|view dashboard')->name('uji-indikator');
    // 7 Action Plan — view actionplans
    Route::get('/action-plans', ActionPlans::class)->middleware('permission:view actionplans|view dashboard')->name('action-plans');
    // 8 Konsensus IBP — view ibp (5 langkah terpadu & persetujuan Direksi/Kadep)
    Route::get('/ibp', \App\Livewire\KonsensusIbp::class)->middleware('permission:view ibp|view dashboard')->name('ibp');
    // 9 Sensitivitas — view sensitivity (Redirect ke simulator sensitivitas aktif)
    Route::get('/sensitivity', function () {
        return redirect()->route('apex-consolidation', ['tab' => 'sensitivity']);
    })->middleware('permission:view sensitivity|view dashboard')->name('sensitivity');
    // 10 Skenario — view skenario (Redirect ke Sandbox Uji Dampak & Skenario What-If)
    Route::get('/skenario', function () {
        return redirect()->route('dampak');
    })->middleware('permission:view skenario|view dampak|view dashboard')->name('skenario');
    // 11 Dokumentasi Metode — view dokumentasi (Panduan polaritas, 5 pilar, DuPont, & 15 rasio)
    Route::get('/dokumentasi', \App\Livewire\DokumentasiMetode::class)->middleware('permission:view dokumentasi|view dashboard')->name('dokumentasi');
    // 12 Gateway & Audit — view gateway (umbrella) + specific
    Route::get('/integration', SystemIntegration::class)->middleware('permission:view integration|view gateway')->name('system-integration');
    Route::get('/staging-logs', StagingLogs::class)->middleware('permission:view staging|view gateway')->name('staging-logs');
    // 13 Super Admin & Konfigurasi — manage users/settings/systeminfo/apikey
    Route::get('/departments', MasterDepartments::class)->middleware('permission:manage settings|manage users|can_manage_users|view objectives')->name('master-departments');
    Route::get('/manage-users', ManageUsers::class)->middleware('permission:manage users|can_manage_users')->name('manage-users');
    Route::get('/settings', AppSettings::class)->middleware('permission:manage settings|view systeminfo|manage apikey')->name('settings');
});
