<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\RatioDefinition;
use App\Support\Bsc\RatioLibrary;
use Illuminate\Database\Seeder;

/**
 * Katalog 19 rasio awal untuk setiap entitas, dengan bobot usulan dari sheet
 * "Asumsi" bagian D. Tiap entitas kemudian dapat menonaktifkan rasio atau
 * mengubah bobotnya lewat menu Katalog Rasio.
 *
 * Idempoten: rasio yang sudah ada tidak ditimpa, sehingga perubahan yang dibuat
 * pengguna tidak hilang saat seeder dijalankan ulang.
 */
class RatioCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Entity::all() as $entity) {
            $urutan = 0;

            foreach (RatioLibrary::all() as $kode => $rasio) {
                RatioDefinition::withoutGlobalScopes()->firstOrCreate(
                    ['entity_id' => $entity->id, 'code' => $kode],
                    ['weight' => $rasio['weight'], 'is_active' => true, 'sort' => ++$urutan]
                );
            }
        }
    }
}
