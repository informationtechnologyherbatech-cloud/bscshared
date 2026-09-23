<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga API entitas: hanya pemanggil yang membawa kunci API aktif milik
 * pemasangan ini yang dilayani. Kunci dibuat di Setting Sistem tab API dan
 * dipasang di .env holding (BSC_SOURCE_<KODE>_KEY).
 */
class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $kunci = (string) ($request->header('X-API-KEY') ?? $request->bearerToken() ?? '');

        if ($kunci === '') {
            return response()->json(['message' => 'Kunci API tidak disertakan (header X-API-KEY).'], 401);
        }

        $tercatat = ApiKey::where('key', $kunci)->where('is_active', true)->first();

        if (! $tercatat) {
            return response()->json(['message' => 'Kunci API tidak dikenal atau sudah dinonaktifkan.'], 401);
        }

        // Kunci yang menyebut entitas lain ditolak, walau databasenya kebetulan sama.
        $pemasangan = strtoupper((string) config('bsc.default_entity'));

        if ($tercatat->entity_code && $pemasangan && $tercatat->entity_code !== $pemasangan) {
            return response()->json(['message' => 'Kunci API itu milik entitas '.$tercatat->entity_code.', bukan '.$pemasangan.'.'], 403);
        }

        // Jejak pemakaian terakhir, paling sering sekali per menit — holding dapat
        // memanggil berkali-kali dan basis data tidak perlu ikut menanggungnya.
        if (! $tercatat->last_used_at || $tercatat->last_used_at->lt(now()->subMinute())) {
            $tercatat->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
