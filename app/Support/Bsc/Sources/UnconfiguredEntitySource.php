<?php

namespace App\Support\Bsc\Sources;

use App\Models\Entity;
use App\Support\Bsc\EntitySummary;

/**
 * Entitas yang sumbernya belum diatur pada pemasangan holding yang berjalan
 * dengan BSC_REQUIRE_ENTITY_SOURCES=true.
 *
 * Tanpa ini, salah ketik pada .env (mis. BSC_SOURCE_AEJ_URL→BSC_SOURCE_AEG_URL)
 * membuat holding diam-diam membaca databasenya SENDIRI lalu menampilkan
 * angkanya seolah-olah milik entitas itu. Lebih baik berbunyi.
 */
class UnconfiguredEntitySource implements EntitySource
{
    /**
     * @param  string  $nama  keterangan singkat sumber untuk di layar
     * @param  string|null  $alasan  pesan yang dibaca pengguna; kosong = belum diatur
     */
    public function __construct(
        private string $nama = 'belum diatur',
        private ?string $alasan = null,
    ) {}

    public function summary(Entity $entitas, string $period): EntitySummary
    {
        return EntitySummary::galat(
            $entitas->code, $entitas->name, $period, $this->name(),
            $this->alasan ?? 'Sumber data '.$entitas->code.' belum diatur: isi BSC_SOURCE_'.$entitas->code.'_URL (+_KEY) atau BSC_SOURCE_'.$entitas->code.'_DB di .env holding, atau aturlah dari halaman Sumber Data Entitas.'
        );
    }

    public function name(): string
    {
        return $this->nama;
    }
}
