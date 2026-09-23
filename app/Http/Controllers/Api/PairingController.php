<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EntityDataSource;
use App\Models\PairingCode;
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
 *     halaman Sumber Data Entitas dibuka. SELAMA berstatus "menunggu" sumbernya
 *     TIDAK dibaca sama sekali (lihat EntitySourceFactory), jadi pendaftaran yang
 *     belum terbukti tidak pernah menjadi angka di layar holding;
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

        // Alamat diperiksa LEBIH DULU, selagi kode belum terpakai: alamat yang
        // ditolak di sini tidak menyentuh jaringan sama sekali, jadi tidak ada
        // gunanya menghanguskan kode pendaftaran karenanya.
        $alamat = rtrim($data['entity_url'], '/');

        if ($pesan = $this->alamatTidakAman($alamat)) {
            return response()->json(['message' => $pesan], 422);
        }

        // Sekali pakai, dan dipakai SEKARANG — termasuk bila entitasnya nanti
        // ternyata tidak cocok. Kode yang ditolak tetap hangus supaya tidak bisa
        // dicoba berulang kali untuk menebak entitas.
        $kode = PairingCode::claim($data['code'], $request->ip());

        if (! $kode) {
            Log::warning('Pendaftaran entitas ditolak: kode tidak berlaku, dari '.$request->ip());

            return response()->json(['message' => 'Kode pendaftaran tidak dikenal, sudah dipakai, atau kedaluwarsa.'], 401);
        }

        $entitas = $kode->entity;

        if (! $entitas || strtoupper($data['entity_code']) !== strtoupper((string) $entitas->code)) {
            Log::warning('Pendaftaran entitas ditolak: kode '.($entitas->code ?? '?').' dipakai untuk '
                .strtoupper($data['entity_code']).', dari '.$request->ip());

            // Pesan tidak menyebutkan entitas mana yang sebenarnya dimaksud.
            return response()->json(['message' => 'Kode pendaftaran tidak cocok dengan entitas ini.'], 403);
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

        $kode->forceFill(['used_url' => $alamat])->save();

        app(EntitySourceSettings::class)->forget();
        app(Consolidation::class)->refreshAll();

        Log::info('Entitas '.$entitas->code.' mendaftar ke holding dari '.$alamat.' ('.$request->ip().'); menunggu pemeriksaan balik.');

        unset($sumber);

        return response()->json(['data' => [
            'entity' => $entitas->code,
            'holding' => config('entity.holding.name'),
            'message' => 'Entitas '.$entitas->name.' berhasil terhubung ke holding.',
        ]]);
    }

    /**
     * Alamat yang akan dipanggil holding. Dua hal dijaga:
     *
     *   - kunci API melintasi jaringan, jadi alamat wajib HTTPS di luar pengembangan;
     *   - alamatnya tidak boleh menunjuk ke dalam jaringan holding sendiri. Kalau
     *     boleh, pemegang kode pendaftaran dapat menyuruh holding memanggil
     *     alamat internalnya dan membaca hasilnya dari pesan galat — pemindai
     *     jaringan gratis yang berjalan dari dalam.
     */
    private function alamatTidakAman(string $alamat): ?string
    {
        $bagian = parse_url($alamat);
        $tuanRumah = (string) ($bagian['host'] ?? '');
        $lokal = in_array($tuanRumah, ['127.0.0.1', 'localhost', '::1'], true);
        $pengembangan = app()->environment(['local', 'testing']);

        if (($bagian['scheme'] ?? '') !== 'https' && ! $lokal && ! $pengembangan) {
            return 'Alamat entitas harus HTTPS.';
        }

        if ($pengembangan) {
            return null;
        }

        if ($lokal) {
            return 'Alamat entitas tidak boleh menunjuk ke server holding sendiri.';
        }

        // Nama yang menunjuk ke alamat pribadi juga ditolak, bukan hanya alamat
        // pribadi yang ditulis langsung.
        // Nama yang tidak dapat dipecahkan dibiarkan lewat: belum tentu keliru
        // (mis. entitas hanya beralamat IPv6), dan penjaga sebenarnya tetap kode
        // pendaftaran + HTTPS.
        $ip = filter_var($tuanRumah, FILTER_VALIDATE_IP) ?: gethostbyname($tuanRumah);

        if (filter_var($ip, FILTER_VALIDATE_IP)
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Alamat entitas harus dapat dihubungi dari jaringan umum, bukan alamat jaringan dalam.';
        }

        return null;
    }
}
