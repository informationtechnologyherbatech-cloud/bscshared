<?php

namespace App\Http\Middleware;

use App\Models\ApiAccessLog;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga API entitas: hanya pemanggil yang membawa kunci API aktif milik
 * pemasangan ini yang dilayani.
 *
 * Kunci dicocokkan lewat sidik jarinya (sha256) — entitas tidak menyimpan kunci
 * yang dapat dipakai. Selain keaktifan, diperiksa pula: kunci milik entitas ini,
 * belum kedaluwarsa, dan IP pemanggil ada di daftar yang diizinkan. Setiap
 * permintaan — diterima maupun ditolak — dicatat di jejak akses.
 */
class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $kunci = (string) ($request->header('X-API-KEY') ?? $request->bearerToken() ?? '');

        if ($kunci === '') {
            return $this->tolak($request, null, 401, ApiAccessLog::TANPA_KUNCI, 'Kunci API tidak disertakan (header X-API-KEY).');
        }

        $tercatat = ApiKey::where('key_hash', ApiKey::fingerprint($kunci))->first();

        if (! $tercatat || ! $tercatat->is_active) {
            // Kunci yang tidak dikenal ditandai dengan potongan SIDIK JARInya,
            // bukan potongan kuncinya: percobaan yang sama tetap dapat dikenali,
            // tanpa menyalin sebagian kunci sungguhan ke dalam jejak akses —
            // kunci yang salah ketik pun tetap kunci orang.
            return $this->tolak($request, null, 401, ApiAccessLog::KUNCI_SALAH,
                'Kunci API tidak dikenal atau sudah dinonaktifkan.', '?'.substr(ApiKey::fingerprint($kunci), 0, 12));
        }

        if ($tercatat->isExpired()) {
            return $this->tolak($request, $tercatat, 401, ApiAccessLog::KADALUWARSA,
                'Kunci API sudah kedaluwarsa pada '.$tercatat->expires_at->format('d/m/Y').'.');
        }

        // Kunci yang menyebut entitas lain ditolak, walau databasenya kebetulan sama.
        $pemasangan = strtoupper((string) config('bsc.default_entity'));

        if ($tercatat->entity_code && $pemasangan && $tercatat->entity_code !== $pemasangan) {
            return $this->tolak($request, $tercatat, 403, ApiAccessLog::ENTITAS_LAIN,
                'Kunci API itu milik entitas '.$tercatat->entity_code.', bukan '.$pemasangan.'.');
        }

        if (! $tercatat->allowsIp($request->ip())) {
            return $this->tolak($request, $tercatat, 403, ApiAccessLog::IP_DITOLAK,
                'Alamat IP pemanggil tidak ada di daftar yang diizinkan untuk kunci ini.');
        }

        // Jejak pemakaian terakhir, paling sering sekali per menit — holding dapat
        // memanggil berkali-kali dan basis data tidak perlu ikut menanggungnya.
        if (! $tercatat->last_used_at || $tercatat->last_used_at->lt(now()->subMinute())) {
            $tercatat->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $respons = $next($request);
        $this->catat($request, $tercatat, $respons->getStatusCode(), ApiAccessLog::DITERIMA, $tercatat->prefix);

        return $respons;
    }

    private function tolak(Request $request, ?ApiKey $kunci, int $status, string $hasil, string $pesan, ?string $prefix = null): Response
    {
        $this->catat($request, $kunci, $status, $hasil, $prefix ?? $kunci?->prefix);

        return response()->json(['message' => $pesan], $status);
    }

    private function catat(Request $request, ?ApiKey $kunci, int $status, string $hasil, ?string $prefix): void
    {
        ApiAccessLog::create([
            'api_key_id' => $kunci?->id,
            'prefix' => $prefix,
            'ip' => $request->ip(),
            'path' => $request->path(),
            'status' => $status,
            'result' => $hasil,
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
