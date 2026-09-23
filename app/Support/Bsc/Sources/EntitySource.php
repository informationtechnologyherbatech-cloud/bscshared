<?php

namespace App\Support\Bsc\Sources;

use App\Models\Entity;
use App\Support\Bsc\EntitySummary;

/**
 * Satu cara mengambil ringkasan kinerja sebuah entitas. Holding tidak perlu tahu
 * entitasnya ada di database yang sama, di database lain, atau di server lain.
 */
interface EntitySource
{
    public function summary(Entity $entitas, string $period): EntitySummary;

    /** Nama sumber untuk ditampilkan: lokal · database · api. */
    public function name(): string;
}
