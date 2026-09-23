<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EntityDataSource;
use App\Models\PairingCode;
use App\Models\Period;
use App\Support\Bsc\Consolidation;
use App\Support\Bsc\Sources\EntitySourceSettings;
use App\Support\EntityContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pendaftaran entitas ke holding (pairing) — dipakai APLIKASI ENTITAS, bukan orang.
 *
 * Holding menerbitkan kode sekali pakai berumur pendek; aplikasi entitas
 * mengirimkan alamat dan kunci APInya sendiri ke sini. Dengan begitu tidak ada
 * kunci yang perlu diketik di holding.
 *
 * Yang menjaga endpoint ini:
 *   - kode pendaftaran sekali pakai, berumur pendek, terikat pada satu entitas;
 *   - holding MEMERIKSA BALIK ke alamat yang dikirim (GET /api/v1/ping memakai
 *     kunci itu), tetapi pada PERMINTAAN LAIN — bukan di tengah permintaan
 *     pendaftaran ini. Selama pendaftaran masih terbuka, aplikasi entitas sedang
 *     sibuk melayaninya dan tidak dapat menjawab panggilan balik; pada server
 *     berpekerja tunggal keduanya akan saling menunggu sampai batas waktu.
 *     Karena itu sumber data disimpan berstatus "menunggu", lalu diperiksa saat
 *     halaman Sumber Data Entitas dibuka atau tombol Uji ditekan. Alamat yang
 *     ternyata keliru dibatalkan di situ;
 *   - alamat wajib HTTPS di luar lingkungan pengembangan;
 *   - hanya pemasangan holding yang melayani; entitas menolaknya (409).
 */
class PairingController extends Controller
{
    public function __invoke(Request $request, EntityContext $context): JsonResponse
    {
        if (! $context->isHoldingMode()) {
            return response()->json(['message' => 'Pemasangan ini bukan holding.'], 409);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'entity_code' => ['required', 'string', 'max:20'],
            'entity_url' => ['required', 'url', 'max:255'],
            'api_key' => ['required', 'string', 'max:255'],
        ]);

        $kode = PairingCode::usable($data['code']);

        if (! $kode) {
            Log::warning('Pendaftaran entitas ditolak: kode tidak berlaku, dari '.$request->ip());

            return response()->json(['message' => 'Kode pendaftaran tidak dikenal, sudah dipakai, atau kedaluwarsa.'], 401);
        }

        $entitas = $kode->entity;

        if (strtoupper($data['entity_code']) !== strtoupper((string) $entitas->code)) {
            return response()->json([
                'message' => 'Kode ini untuk entitas '.$entitas->code.', bukan '.strtoupper($data['entity_code']).'.',
            ], 403);
        }

        $alamat = rtrim($data['entity_url'], '/');

        if ($pesan = $this->alamatTidakAman($alamat)) {
            return response()->json(['message' => $pesan], 422);
        }

        $sumber = EntityDataSource::updateOrCreate(['entity_id' => $entitas->id], [
            'driver' => EntityDataSource::API,
            'api_url' => $alamat,
            'api_key' => $data['api_key'],          // tersimpan terenkripsi
            'database_name' => null,
            'db_host' => null, 'db_port' => null, 'db_username' => null, 'db_password' => null,
            'last_status' => 'menunggu',
            'last_message' => 'didaftarkan sendiri oleh aplikasi entitas; menunggu pemeriksaan balik.',
            'last_checked_at' => now(),
        ]);

        $kode->forceFill(['used_at' => now(), 'used_ip' => $request->ip(), 'used_url' => $alamat])->save();

        app(EntitySourceSettings::class)->forget();
        app(Consolidation::class)->refresh((string) Period::active());

        Log::info('Entitas '.$entitas->code.' mendaftar ke holding dari '.$alamat.' ('.$request->ip().'); menunggu pemeriksaan balik.');

        unset($sumber);

        return response()->json(['data' => [
            'entity' => $entitas->code,
            'holding' => config('entity.holding.name'),
            'message' => 'Entitas '.$entitas->name.' berhasil terhubung ke holding.',
        ]]);
    }

    /** Kunci API melintasi jaringan: alamat wajib HTTPS di luar pengembangan. */
    private function alamatTidakAman(string $alamat): ?string
    {
        $bagian = parse_url($alamat);
        $lokal = in_array($bagian['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true);

        if (($bagian['scheme'] ?? '') !== 'https' && ! $lokal && ! app()->environment(['local', 'testing'])) {
            return 'Alamat entitas harus HTTPS.';
        }

        return null;
    }
}
