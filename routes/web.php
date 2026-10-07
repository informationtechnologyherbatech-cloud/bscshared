<?php

use App\Livewire\AccountBalances;
use App\Livewire\AccountPostMap;
use App\Livewire\ActionPlans;
use App\Livewire\AppSettings;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Auth\Login;
use App\Livewire\BscDashboard;
use App\Livewire\BscWiring;
use App\Livewire\DepartmentObjectives;
use App\Livewire\EntitySources;
use App\Livewire\FinancialRatios;
use App\Livewire\HoldingConsolidation;
use App\Livewire\IndicatorTests;
use App\Livewire\KpiCascades;
use App\Livewire\ManageUsers;
use App\Livewire\MethodDocumentation;
use App\Livewire\RatioCatalog;
use App\Livewire\RevenuePlanning;
use App\Livewire\RevenueTargets;
use App\Livewire\StagingLogs;
use App\Livewire\SystemIntegration;
use App\Livewire\WorkUnits;
use App\Models\Entity;
use App\Models\Period;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Guest: Login
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

// Authenticated: Logout
Route::post('/logout', function (Request $request) {
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

// Protected App Routes — menu PRD §4 Tabel 4 (permission enforced server-side §9).
// Lima menu simulasi (Dampak/What-If, Simulasi CoA, Konsensus IBP, Sensitivitas,
// Skenario) dihapus 2026-10-07: tidak dipakai, dan keempatnya hanya halaman kosong.
Route::middleware(['auth', 'active', 'password.change'])->group(function () {
    // 1 Piramida — view dashboard (semua peran punya)
    Route::get('/', BscDashboard::class)->middleware('permission:view dashboard')->name('dashboard');
    // 2 Rasio — view ratios (read), manage ratios (write)
    Route::get('/ratios', FinancialRatios::class)->middleware('permission:view ratios|view dashboard')->name('financial-ratios');
    // 3 Objective — view objectives
    Route::get('/objectives', DepartmentObjectives::class)->middleware('permission:view objectives|view dashboard')->name('department-objectives');
    // 4 Wiring — view wiring
    Route::get('/wiring', BscWiring::class)->middleware('permission:view wiring')->name('bsc-wiring');
    // 7 Action Plan — view actionplans
    Route::get('/action-plans', ActionPlans::class)->middleware('permission:view actionplans|view dashboard')->name('action-plans');
    // 11 Dokumentasi Metode — view dokumentasi: panduan pengisian, metode skoring, uji mandiri 12 pemeriksaan.
    Route::get('/dokumentasi', MethodDocumentation::class)->middleware('permission:view dokumentasi')->name('dokumentasi');
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

    // Sumber data tiap entitas (pemasangan holding): alamat API + kunci, atau nama
    // database. Komponennya sendiri menolak pengguna yang terikat satu entitas.
    Route::get('/sumber-entitas', EntitySources::class)->middleware('permission:view consolidation')->name('entity-sources');

    // Struktur unit kerja per entitas — sumber daftar departemen.
    Route::get('/unit-kerja', WorkUnits::class)->middleware('permission:manage units')->name('work-units');

    // Pengalih entitas bagi pengguna level holding. Pengguna yang terikat satu
    // entitas tidak dapat berpindah; permintaannya ditolak.
    Route::post('/entitas/aktif', function (Request $request, EntityContext $context) {
        $entityId = (int) $request->input('entity_id');

        abort_unless($context->switchTo($request->user(), $entityId), 403, 'Anda tidak memiliki akses ke entitas tersebut.');

        return redirect()->back()->with('message', 'Beralih ke entitas '.Entity::find($entityId)?->name.'.');
    })->name('entity.switch');

    // Periode aktif (navbar) — berlaku di semua halaman. Parameter periode/tahun di
    // URL halaman asal dibuang agar halaman mengikuti periode yang baru dipilih.
    Route::post('/periode/aktif', function (Request $request) {
        $periode = (string) $request->input('period');

        if (! Period::setActive($periode)) {
            return redirect()->back()->with('error', 'Periode '.$periode.' belum dibuat.');
        }

        // Hanya kembali ke halaman aplikasi ini (Referer dari host lain diabaikan).
        $asal = url()->previous();
        if (! in_array(parse_url($asal, PHP_URL_HOST), [$request->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)], true)) {
            $asal = route('dashboard');
        }
        $bagian = parse_url($asal);
        parse_str($bagian['query'] ?? '', $query);
        unset($query['period'], $query['selectedPeriod'], $query['year']);
        $tujuan = strtok($asal, '?').($query ? '?'.http_build_query($query) : '');

        return redirect()->to($tujuan);
    })->name('period.switch');

    // Cara membaca periode: kumulatif sejak Januari (bawaan) atau bulan terpilih
    // saja. Disimpan di sesi seperti periode aktif, sehingga berlaku di semua
    // halaman tanpa perlu dipilih ulang.
    Route::post('/periode/kumulatif', function (Request $request) {
        Period::setCumulative($request->boolean('cumulative'));

        $asal = url()->previous();
        if (! in_array(parse_url($asal, PHP_URL_HOST), [$request->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)], true)) {
            $asal = route('dashboard');
        }

        return redirect()->to($asal);
    })->name('period.cumulative');
});
