<?php

namespace Database\Seeders;

use App\Models\AccountPostDefinition;
use App\Models\Entity;
use App\Support\Bsc\AccountPosts;
use Illuminate\Database\Seeder;

/**
 * Katalog 16 pos akun awal untuk setiap entitas, dari workbook (sheet "Asumsi"
 * bagian F). Sesudah ini tiap entitas boleh mengubah nama/keterangan pos,
 * menonaktifkan yang tidak dipakai, atau menambah pos sendiri lewat menu
 * Pos Akun.
 *
 * Idempoten: pos yang sudah ada tidak ditimpa, sehingga perubahan pengguna
 * tidak hilang bila seeder dijalankan ulang.
 */
class AccountPostCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Entity::all() as $entity) {
            $urutan = 0;

            foreach (AccountPosts::builtins() as $kode => $pos) {
                AccountPostDefinition::withoutGlobalScopes()->firstOrCreate(
                    ['entity_id' => $entity->id, 'code' => $kode],
                    [
                        'name' => $pos['name'],
                        'kind' => $pos['kind'],
                        'source' => $pos['source'],
                        'hint' => $pos['hint'],
                        'is_active' => true,
                        'is_builtin' => true,
                        'sort' => ++$urutan,
                    ]
                );
            }
        }

        AccountPosts::forget();
    }
}
