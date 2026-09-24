<?php

namespace App\Support\Bsc\Integration;

use App\Models\OdooConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien JSON-RPC Odoo — secukupnya untuk MEMBACA.
 *
 * Odoo melayani /jsonrpc dengan dua layanan: `common` untuk masuk (menukar
 * nama pengguna + kunci API menjadi uid) dan `object` untuk memanggil metode
 * model. Tidak ada satu pun pemanggilan tulis di kelas ini; pengguna Odoo yang
 * dipakai pun cukup diberi hak baca jurnal.
 *
 * Galat dari Odoo datang sebagai HTTP 200 dengan isi {"error": …}, jadi status
 * HTTP saja tidak cukup untuk menilai berhasil atau tidak.
 */
class OdooClient
{
    private ?int $uid = null;

    public function __construct(
        private string $baseUrl,
        private string $database,
        private string $username,
        private string $apiKey,
    ) {}

    public static function for(OdooConnection $sambungan): self
    {
        return new self(
            rtrim($sambungan->base_url, '/'),
            $sambungan->database_name,
            $sambungan->username,
            (string) $sambungan->api_key,
        );
    }

    /** uid pengguna Odoo; sekaligus pembuktian bahwa kredensialnya benar. */
    public function login(): int
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $uid = $this->call('common', 'login', [$this->database, $this->username, $this->apiKey]);

        if (! is_int($uid) || $uid <= 0) {
            throw new RuntimeException('Odoo menolak nama pengguna atau kunci API.');
        }

        return $this->uid = $uid;
    }

    /**
     * Panggil metode sebuah model, mis. search_read / read_group.
     *
     * @param  array<int, mixed>  $args
     * @param  array<string, mixed>  $kwargs
     */
    public function execute(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        return $this->call('object', 'execute_kw', [
            $this->database, $this->login(), $this->apiKey, $model, $method, $args, $kwargs,
        ]);
    }

    /**
     * Daftar akun buku besar, untuk membantu mengisi pemetaan dari layar.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public function accounts(int $limit = 500): array
    {
        $baris = $this->execute('account.account', 'search_read', [[]], [
            'fields' => ['code', 'name'],
            'limit' => $limit,
            'order' => 'code asc',
        ]);

        return collect(is_array($baris) ? $baris : [])
            ->map(fn ($a) => ['code' => (string) ($a['code'] ?? ''), 'name' => (string) ($a['name'] ?? '')])
            ->filter(fn ($a) => $a['code'] !== '')
            ->values()->all();
    }

    /**
     * Jumlah mutasi jurnal TERBUKUKAN per kode akun dalam satu rentang tanggal.
     *
     * Hanya jurnal berstatus `posted` yang dihitung — draf belum menjadi angka
     * resmi. Saldo Odoo adalah debit − kredit, jadi akun bersaldo kredit
     * (penjualan, utang, ekuitas) bernilai negatif di sini; pembalikan tandanya
     * diatur pada pemetaan akun, bukan di sini.
     *
     * @return array<string, float> kode akun => saldo
     */
    public function balances(string $sejak, string $sampai): array
    {
        $baris = $this->execute('account.move.line', 'read_group', [
            [
                ['parent_state', '=', 'posted'],
                ['date', '>=', $sejak],
                ['date', '<=', $sampai],
            ],
            ['balance:sum'],
            ['account_id'],
        ], ['lazy' => false]);

        $hasil = [];

        foreach (is_array($baris) ? $baris : [] as $b) {
            // account_id datang sebagai [id, "KODE Nama Akun"].
            $akun = $b['account_id'] ?? null;
            $kode = is_array($akun) ? trim(strtok((string) ($akun[1] ?? ''), ' ')) : null;

            if (! $kode) {
                continue;
            }

            $hasil[$kode] = ($hasil[$kode] ?? 0.0) + (float) ($b['balance'] ?? 0);
        }

        return $hasil;
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function call(string $service, string $method, array $args): mixed
    {
        try {
            $respons = Http::acceptJson()
                ->withoutRedirecting()
                ->timeout((int) config('bsc.odoo_timeout', 30))
                ->post($this->baseUrl.'/jsonrpc', [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => ['service' => $service, 'method' => $method, 'args' => $args],
                    'id' => random_int(1, PHP_INT_MAX),
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Server Odoo tidak dapat dihubungi: '.$e->getMessage(), 0, $e);
        }

        if (! $respons->successful()) {
            throw new RuntimeException('Odoo menjawab status '.$respons->status().'.');
        }

        $isi = $respons->json();

        if (isset($isi['error'])) {
            // Pesan Odoo bertingkat; yang paling berguna biasanya data.message.
            $pesan = $isi['error']['data']['message'] ?? $isi['error']['message'] ?? 'galat tidak dijelaskan';

            throw new RuntimeException('Odoo menolak permintaan: '.$pesan);
        }

        return $isi['result'] ?? null;
    }
}
