<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\RatioDefinition;
use App\Support\Bsc\RatioLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog 19 rasio awal untuk setiap entitas, dengan bobot usulan dari sheet
 * "Asumsi" bagian D. Tiap entitas kemudian dapat menonaktifkan rasio, mengubah
 * bobotnya, mengubah rumusnya, atau menambah rasio sendiri lewat menu Katalog
 * Rasio.
 *
 * Idempoten: rasio yang sudah ada tidak ditimpa, sehingga perubahan yang dibuat
 * pengguna tidak hilang saat seeder dijalankan ulang.
 */
class RatioCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder ini juga dipanggil migrasi 2026_09_22_130000, saat kolom rumus
        // belum ada; di sana cukup kolom aslinya.
        $adaKolomRumus = Schema::hasColumn('ratio_definitions', 'expression');

        foreach (Entity::all() as $entity) {
            $urutan = 0;

            foreach (RatioLibrary::all() as $kode => $rasio) {
                $tambahan = ! $adaKolomRumus ? [] : [
                    'name' => $rasio['name'],
                    'ratio_group' => $rasio['group'],
                    'formula' => $rasio['formula'],
                    // Kolom inilah yang benar-benar dihitung mesin rumus.
                    'expression' => $rasio['expression'],
                    'unit' => $rasio['unit'],
                    'polarity' => $rasio['polarity'],
                    'is_builtin' => true,
                ];

                RatioDefinition::withoutGlobalScopes()->firstOrCreate(
                    ['entity_id' => $entity->id, 'code' => $kode],
                    ['weight' => $rasio['weight'], 'is_active' => true, 'sort' => ++$urutan] + $tambahan
                );
            }
        }
    }
}
