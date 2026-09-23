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
    public function summary(Entity $entitas, string $period): EntitySummary
    {
        return EntitySummary::galat(
            $entitas->code, $entitas->name, $period, $this->name(),
            'Sumber data '.$entitas->code.' belum diatur: isi BSC_SOURCE_'.$entitas->code.'_URL (+_KEY) atau BSC_SOURCE_'.$entitas->code.'_DB di .env holding.'
        );
    }

    public function name(): string
    {
        return 'belum diatur';
    }
}
