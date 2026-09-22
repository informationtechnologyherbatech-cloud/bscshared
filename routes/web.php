<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Auth\Login;
use App\Livewire\BscDashboard;
use App\Livewire\FinancialRatios;
use App\Livewire\DepartmentObjectives;
use App\Livewire\ActionPlans;
use App\Livewire\StagingLogs;
use App\Livewire\SystemIntegration;
use App\Livewire\BscWiring;
use App\Livewire\ManageUsers;
use App\Livewire\AppSettings;
use App\Livewire\AccountBalances;
use App\Livewire\AccountPostMap;
use App\Livewire\HoldingConsolidation;
use App\Livewire\IndicatorTests;
use App\Livewire\KpiCascades;
use App\Livewire\RatioCatalog;
use App\Livewire\RevenuePlanning;
use App\Livewire\RevenueTargets;
use App\Livewire\WorkUnits;
use App\Support\EntityContext;

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

// Ganti kata sandi — satu-satunya halaman yang tetap terbuka bagi pengguna
// yang kata sandinya ditandai wajib diganti.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/ubah-password', ChangePassword::class)->name('password.change');
});

// Protected App Routes — 13 Menu PRD §4 Tabel 4 (permission enforced server-side §9)
Route::middleware(['auth', 'active', 'password.change'])->group(function () {
    // 1 Piramida — view dashboard (semua peran punya)
    Route::get('/', BscDashboard::class)->middleware('permission:view dashboard')->name('dashboard');
    // 2 Rasio — view ratios (read), manage ratios (write)
    Route::get('/ratios', FinancialRatios::class)->middleware('permission:view ratios|view dashboard')->name('financial-ratios');
    // 3 Objective — view objectives
    Route::get('/objectives', DepartmentObjectives::class)->middleware('permission:view objectives|view dashboard')->name('department-objectives');
    // 4 Wiring — view wiring
    Route::get('/wiring', BscWiring::class)->middleware('permission:view wiring')->name('bsc-wiring');
    // 5 Dampak / What-If Sandbox — view dampak (Super Admin, FAT, Kadep)
    Route::get('/dampak', \App\Livewire\ComingSoon::class)->middleware('permission:view dampak')->name('dampak')
        ->defaults('title', 'Uji Dampak / What-If Sandbox')->defaults('desc', 'Sandbox simulasi KPI hipotetis — pratinjau debet-kredit tanpa menyentuh data produksi.');
    // 6 Simulasi CoA — view coa (Super Admin, FAT)
    Route::get('/coa', \App\Livewire\ComingSoon::class)->middleware('permission:view coa')->name('coa')
        ->defaults('title', 'Simulasi CoA')->defaults('desc', 'Stress-test bagan akun hipotetis — konsisten rumus rasio Menu 2.');
    // 7 Action Plan — view actionplans
    Route::get('/action-plans', ActionPlans::class)->middleware('permission:view actionplans|view dashboard')->name('action-plans');
    // 8 Konsensus IBP — view ibp
    Route::get('/ibp', \App\Livewire\ComingSoon::class)->middleware('permission:view ibp')->name('ibp')
        ->defaults('title', 'Konsensus IBP')->defaults('desc', 'IBP 5 langkah: Product→Demand→Supply→Rekonsiliasi Finansial→MBR, proyeksi 12 bulan.');
    // 9 Sensitivitas — view sensitivity
    Route::get('/sensitivity', \App\Livewire\ComingSoon::class)->middleware('permission:view sensitivity')->name('sensitivity')
        ->defaults('title', 'Sensitivitas')->defaults('desc', 'Margin keamanan rasio terhadap skenario normal/moderat/krisis.');
    // 10 Skenario — view skenario
    Route::get('/skenario', \App\Livewire\ComingSoon::class)->middleware('permission:view skenario')->name('skenario')
        ->defaults('title', 'Skenario')->defaults('desc', 'Simpan/muat skenario, undo/redo 50 langkah.');
    // 11 Dokumentasi Metode — view dokumentasi: panduan pengisian, metode skoring, uji mandiri 12 pemeriksaan.
    Route::get('/dokumentasi', \App\Livewire\MethodDocumentation::class)->middleware('permission:view dokumentasi')->name('dokumentasi');
    // 12 Gateway & Audit — view gateway (umbrella) + specific
    Route::get('/integration', SystemIntegration::class)->middleware('permission:view integration|view gateway')->name('system-integration');
    Route::get('/staging-logs', StagingLogs::class)->middleware('permission:view staging|view gateway')->name('staging-logs');
    // 13 Super Admin & Konfigurasi — manage users/settings/systeminfo/apikey
    Route::get('/manage-users', ManageUsers::class)->middleware('permission:manage users|can_manage_users')->name('manage-users');
    Route::get('/settings', AppSettings::class)->middleware('permission:manage settings|view systeminfo|manage apikey')->name('settings');

    // Tingkat 1 — target & realisasi revenue bulanan (sumber F1 skor puncak).
    Route::get('/revenue', RevenueTargets::class)->middleware('permission:manage revenue|view dashboard')->name('revenue');
    // Penyusunan target setahun (L1 bagian A–G): CAGR, regresi, bottom-up, Ansoff, SWOT, rekonsiliasi.
    Route::get('/revenue/perencanaan', RevenuePlanning::class)->middleware('permission:manage revenue|view dashboard')->name('revenue-planning');

    // Tingkat 2 — pos akun (sumber 19 rasio) dan katalog rasio per entitas.
    Route::get('/pos-akun', AccountBalances::class)->middleware('permission:manage ratios|view ratios')->name('account-balances');
    Route::get('/katalog-rasio', RatioCatalog::class)->middleware('permission:manage ratios|view ratios')->name('ratio-catalog');

    // Tingkat 3 — peta pos akun × unit dan cascade KPI Head → Supervisor → Staff.
    Route::get('/peta-pos-akun', AccountPostMap::class)->middleware('permission:manage ratios|view ratios|view objectives')->name('account-post-map');
    Route::get('/cascade-kpi', KpiCascades::class)->middleware('permission:view objectives|manage ratios')->name('kpi-cascades');

    // Tingkat 4 — uji indikator (Uji A & B) sebelum KPI masuk monitoring.
    Route::get('/uji-indikator', IndicatorTests::class)->middleware('permission:manage ratios|view objectives')->name('indicator-tests');

    // Konsolidasi holding — hanya pengguna level holding (dicek juga di komponen).
    Route::get('/konsolidasi', HoldingConsolidation::class)->middleware('permission:view consolidation')->name('consolidation');

    // Struktur unit kerja per entitas — sumber daftar departemen.
    Route::get('/unit-kerja', WorkUnits::class)->middleware('permission:manage units')->name('work-units');

    // Pengalih entitas bagi pengguna level holding. Pengguna yang terikat satu
    // entitas tidak dapat berpindah; permintaannya ditolak.
    Route::post('/entitas/aktif', function (\Illuminate\Http\Request $request, EntityContext $context) {
        $entityId = (int) $request->input('entity_id');

        abort_unless($context->switchTo($request->user(), $entityId), 403, 'Anda tidak memiliki akses ke entitas tersebut.');

        return redirect()->back()->with('message', 'Beralih ke entitas '.\App\Models\Entity::find($entityId)?->name.'.');
    })->name('entity.switch');

    // Periode aktif (navbar) — berlaku di semua halaman. Parameter periode/tahun di
    // URL halaman asal dibuang agar halaman mengikuti periode yang baru dipilih.
    Route::post('/periode/aktif', function (\Illuminate\Http\Request $request) {
        $periode = (string) $request->input('period');

        if (! \App\Models\Period::setActive($periode)) {
            return redirect()->back()->with('error', 'Periode '.$periode.' belum dibuat.');
        }

        $asal = url()->previous();
        $bagian = parse_url($asal);
        parse_str($bagian['query'] ?? '', $query);
        unset($query['period'], $query['selectedPeriod'], $query['year']);
        $tujuan = strtok($asal, '?').($query ? '?'.http_build_query($query) : '');

        return redirect()->to($tujuan);
    })->name('period.switch');
});
