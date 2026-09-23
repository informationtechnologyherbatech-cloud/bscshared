<?php

namespace App\Support\Bsc\Sources;

use App\Models\Entity;
use App\Models\EntityDataSource;
use App\Support\Bsc\EntitySummaryBuilder;
use App\Support\EntityContext;

/**
 * Memilih cara membaca sebuah entitas: API (server lain) → database terpisah →
 * lokal (database ini juga). Sumbernya diatur di layar Setting → Sumber Data
 * Entitas, atau lewat .env; lihat EntitySourceSettings.
 */
class EntitySourceFactory
{
    public function __construct(
        private EntityContext $context,
        private EntitySummaryBuilder $builder,
        private EntitySourceSettings $settings,
    ) {}

    public function for(Entity $entitas): EntitySource
    {
        $sumber = $this->settings->for($entitas);

        // Sumber yang belum tentu sah tidak dibaca sama sekali — bukan dialihkan
        // ke database sendiri, karena itu justru menampilkan angka yang keliru.
        if ($tertahan = $this->tertahan($entitas, $sumber['origin'])) {
            return $tertahan;
        }

        if ($sumber['driver'] === EntityDataSource::API && ! empty($sumber['api_url'])) {
            return new ApiEntitySource((string) $sumber['api_url'], $sumber['api_key'] ?? null);
        }

        if ($sumber['driver'] === EntityDataSource::DATABASE && ! empty($sumber['database'])) {
            return new DatabaseEntitySource($this->context, $this->builder, (string) $sumber['database'], (array) ($sumber['db'] ?? []));
        }

        // Pemasangan holding yang ketat menolak membaca databasenya sendiri ketika
        // sumbernya memang belum pernah diatur (salah ketik .env, entitas baru).
        // Memilih "lokal" di layar tetap dihormati: itu keputusan sadar.
        if (config('bsc.require_entity_sources') && $this->context->isHoldingMode() && $sumber['origin'] === 'tidak diatur') {
            return new UnconfiguredEntitySource;
        }

        return new LocalEntitySource($this->context, $this->builder);
    }

    /**
     * Keadaan yang membuat sebuah sumber tidak boleh dibaca, apa pun drivernya.
     */
    private function tertahan(Entity $entitas, string $origin): ?EntitySource
    {
        return match ($origin) {
            EntitySourceSettings::MENUNGGU => new UnconfiguredEntitySource('menunggu verifikasi',
                'Pendaftaran '.$entitas->code.' belum diperiksa balik oleh holding, jadi angkanya belum dibaca. Buka Sumber Data Entitas untuk menyelesaikannya.'),
            EntitySourceSettings::RUSAK => new UnconfiguredEntitySource('perlu diatur ulang',
                'Kredensial '.$entitas->code.' tidak dapat dibuka (kunci aplikasi berganti). Atur ulang sumber datanya.'),
            default => null,
        };
    }

    /** Keterangan singkat sumber tiap entitas untuk halaman Konsolidasi & dokumentasi. */
    public function describe(Entity $entitas): string
    {
        $sumber = $this->settings->for($entitas);

        return match (true) {
            $sumber['origin'] === EntitySourceSettings::MENUNGGU => 'menunggu verifikasi',
            $sumber['origin'] === EntitySourceSettings::RUSAK => 'perlu diatur ulang',
            $sumber['driver'] === EntityDataSource::API && ! empty($sumber['api_url']) => 'API '.preg_replace('#^https?://#', '', (string) $sumber['api_url']),
            $sumber['driver'] === EntityDataSource::DATABASE && ! empty($sumber['database']) => 'database '.$sumber['database'],
            config('bsc.require_entity_sources') && $this->context->isHoldingMode() && $sumber['origin'] === 'tidak diatur' => 'belum diatur',
            default => 'database aplikasi ini',
        };
    }
}
