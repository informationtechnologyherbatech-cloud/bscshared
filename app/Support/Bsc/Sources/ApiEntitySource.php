<?php

namespace App\Support\Bsc\Sources;

use App\Models\Entity;
use App\Support\Bsc\EntitySummary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entitas yang berdiri di SERVER SENDIRI. Holding memanggil endpoint ringkasan
 * milik entitas itu dengan kunci API; yang diterima hanya angka ringkasan —
 * database holding tidak menyimpan apa pun dari entitas.
 */
class ApiEntitySource implements EntitySource
{
    public function __construct(
        private string $baseUrl,
        private ?string $apiKey,
    ) {}

    public function summary(Entity $entitas, string $period): EntitySummary
    {
        $alamat = rtrim($this->baseUrl, '/').'/api/v1/consolidation';

        if ($tolak = $this->alamatTidakAman($alamat)) {
            return EntitySummary::galat($entitas->code, $entitas->name, $period, $this->name(), $tolak);
        }

        try {
            $respons = Http::withHeaders(array_filter(['X-API-KEY' => $this->apiKey]))
                ->acceptJson()
                // Kunci API tidak boleh ikut berpindah ke alamat lain: server entitas
                // yang dibajak dapat mengalihkan permintaan untuk memanen kuncinya.
                ->withoutRedirecting()
                ->timeout((int) config('bsc.api_timeout', 8))
                ->get($alamat, ['period' => $period]);

            if ($respons->redirect()) {
                return EntitySummary::galat(
                    $entitas->code, $entitas->name, $period, $this->name(),
                    'Server entitas mengalihkan permintaan; alamat pada .env perlu diperbaiki.'
                );
            }

            // Jawaban raksasa tidak ikut diurai — holding tidak boleh kehabisan memori
            // karena satu entitas.
            $batas = (int) config('bsc.api_max_bytes', 2 * 1024 * 1024);
            if (strlen((string) $respons->body()) > $batas) {
                return EntitySummary::galat($entitas->code, $entitas->name, $period, $this->name(), 'Jawaban API terlalu besar.');
            }

            if ($respons->failed()) {
                return EntitySummary::galat(
                    $entitas->code, $entitas->name, $period, $this->name(),
                    'Server entitas menjawab '.$respons->status().'.'
                );
            }

            $isi = $respons->json('data');

            if (! is_array($isi)) {
                return EntitySummary::galat($entitas->code, $entitas->name, $period, $this->name(), 'Jawaban API tidak dikenali.');
            }

            // Kode entitas wajib ada dan cocok; tanpa itu jawaban tidak dapat
            // dipastikan milik entitas yang diminta.
            if (($isi['code'] ?? null) !== $entitas->code) {
                // Alamat salah pasang: server itu melayani entitas lain.
                return EntitySummary::galat(
                    $entitas->code, $entitas->name, $period, $this->name(),
                    'Server itu melayani entitas '.($isi['code'] ?? '(tidak disebutkan)').', bukan '.$entitas->code.'.'
                );
            }

            return EntitySummary::fromArray($isi + ['name' => $entitas->name], $this->name());
        } catch (Throwable $e) {
            Log::warning('Ringkasan entitas '.$entitas->code.' gagal diambil: '.$e->getMessage());

            return EntitySummary::galat($entitas->code, $entitas->name, $period, $this->name(), 'Server entitas tidak dapat dihubungi.');
        }
    }

    /**
     * Alamat entitas wajib HTTPS, kecuali di lingkungan pengembangan atau bila
     * menunjuk ke mesin ini sendiri — kunci API melintasi jaringan.
     */
    private function alamatTidakAman(string $alamat): ?string
    {
        $bagian = parse_url($alamat);

        if (! $bagian || empty($bagian['host'])) {
            return 'Alamat entitas pada .env tidak dikenali.';
        }

        $lokal = in_array($bagian['host'], ['127.0.0.1', 'localhost', '::1'], true);

        if (($bagian['scheme'] ?? '') !== 'https' && ! $lokal && ! app()->environment(['local', 'testing'])) {
            return 'Alamat entitas harus HTTPS agar kunci API tidak melintas terbuka.';
        }

        return null;
    }

    public function name(): string
    {
        return EntitySummary::SUMBER_API;
    }
}
