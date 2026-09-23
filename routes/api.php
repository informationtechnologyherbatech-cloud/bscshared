<?php

use App\Http\Controllers\Api\EntitySummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Entitas → Holding
|--------------------------------------------------------------------------
|
| Dipakai HANYA oleh aplikasi holding (EMC) untuk membaca ringkasan kinerja
| entitas ini saat halaman Konsolidasi dibuka; holding tidak menyimpan datanya.
| Semua permintaan wajib membawa kunci API aktif (header X-API-KEY) yang dibuat
| di Setting Sistem tab API.
|
| Yang tersedia hanya ringkasan — tidak ada endpoint untuk pos akun, isi sasaran
| mutu, atau program kerja, sehingga data entitas tetap tertutup bagi siapa pun
| di luar entitas ini.
|
*/

Route::middleware(['throttle:60,1', 'api.key'])->prefix('v1')->group(function () {
    Route::get('/consolidation', EntitySummaryController::class)->name('api.consolidation');

    // Pemeriksaan sambungan dari holding: tidak memuat angka kinerja apa pun.
    Route::get('/ping', fn () => response()->json([
        'data' => [
            'entity' => entity_name(),
            'entity_code' => config('bsc.default_entity'),
            'app' => app_display_name(),
            'version' => app_version(),
            'time' => now()->toIso8601String(),
        ],
    ]))->name('api.ping');
});
