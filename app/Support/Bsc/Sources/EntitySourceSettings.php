<?php

namespace App\Support\Bsc\Sources;

use App\Models\Entity;
use App\Models\EntityDataSource;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Sumber data sebuah entitas menurut dua tempat, dengan urutan:
 *
 *   1. Pengaturan di layar (Setting → Sumber Data Entitas) — disimpan di tabel
 *      entity_sources, kunci API terenkripsi;
 *   2. berkas .env (BSC_SOURCE_<KODE>_URL/_KEY/_DB) — untuk penyebaran otomatis
 *      atau pemasangan yang belum memakai layar tersebut.
 *
 * Layar menang; .env menjadi nilai awal. Dibaca berkali-kali dalam satu
 * permintaan, jadi hasilnya diingat sebentar di memori.
 */
class EntitySourceSettings
{
    /**
     * Baris pengaturan yang sudah dibaca dalam permintaan ini (false = belum ada).
     *
     * @var array<int, EntityDataSource|false>
     */
    private array $memo = [];

    /**
     * @return array{driver: string, api_url: ?string, api_key: ?string, database: ?string, db: array<string, ?string>, origin: string}
     */
    public function for(Entity $entitas): array
    {
        return $this->resolve($entitas);
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    /**
     * Baris pengaturan layar untuk entitas ini, bila tabelnya sudah ada. Hanya
     * baris inilah yang diingat dalam satu permintaan; nilai .env selalu dibaca
     * segar supaya perubahan konfigurasi langsung berlaku.
     */
    public function record(Entity $entitas): ?EntityDataSource
    {
        if (array_key_exists($entitas->id, $this->memo)) {
            return $this->memo[$entitas->id] ?: null;
        }

        try {
            $baris = Schema::hasTable('entity_sources')
                ? EntityDataSource::where('entity_id', $entitas->id)->first()
                : null;
        } catch (Throwable) {
            // Mis. saat migrasi pertama kali berjalan.
            $baris = null;
        }

        $this->memo[$entitas->id] = $baris ?: false;

        return $baris;
    }

    /**
     * @return array{driver: string, api_url: ?string, api_key: ?string, database: ?string, origin: string}
     */
    private function resolve(Entity $entitas): array
    {
        $baris = $this->record($entitas);

        if ($baris) {
            return [
                'driver' => $baris->driver,
                'api_url' => $baris->api_url,
                'api_key' => $baris->api_key,
                'database' => $baris->database_name,
                // Kredensial baca-saja khusus entitas ini; kosong = kredensial aplikasi ini.
                'db' => [
                    'host' => $baris->db_host,
                    'port' => $baris->db_port,
                    'username' => $baris->db_username,
                    'password' => $baris->db_password,
                ],
                'origin' => 'layar',
            ];
        }

        $env = (array) config('bsc.sources.'.$entitas->code, []);

        return [
            'driver' => match (true) {
                ! empty($env['api_url']) => EntityDataSource::API,
                ! empty($env['database']) => EntityDataSource::DATABASE,
                default => EntityDataSource::LOKAL,
            },
            'api_url' => $env['api_url'] ?? null,
            'api_key' => $env['api_key'] ?? null,
            'database' => $env['database'] ?? null,
            'db' => ['host' => null, 'port' => null, 'username' => null, 'password' => null],
            'origin' => empty($env['api_url']) && empty($env['database']) ? 'tidak diatur' : '.env',
        ];
    }
}
