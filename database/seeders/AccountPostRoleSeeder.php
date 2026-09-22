<?php

namespace Database\Seeders;

use App\Models\AccountPostRole;
use App\Models\Entity;
use Illuminate\Database\Seeder;

/**
 * Peta bagian 2 awal — siapa Pemilik (O) / Kontributor (K) tiap pos akun.
 *
 * Hanya Erdigma yang punya sumbernya (workbook, status "usulan awal, sahkan
 * bersama CFO"). Entitas manufaktur dibiarkan kosong untuk ditetapkan lewat
 * menu Peta Pos Akun — menebak kepemilikan pos akun mereka justru berbahaya
 * karena menentukan siapa yang dibebani target rupiah.
 *
 * Idempoten: peran yang sudah ada tidak ditimpa.
 */
class AccountPostRoleSeeder extends Seeder
{
    /** Sheet "Peta Rasio-Akun-Dept" baris 31–47. */
    public const ERDIGMA = [
        'MFG' => ['PA02' => 'K', 'PA05' => 'K'],
        'PDV' => ['PA01' => 'K', 'PA02' => 'K', 'PA05' => 'K'],
        'SCM' => ['PA01' => 'K', 'PA02' => 'O', 'PA05' => 'O', 'PA07' => 'K', 'PA08' => 'K', 'PA09' => 'K', 'PA10' => 'K', 'PA11' => 'K', 'PA15' => 'K', 'PA16' => 'K'],
        'CMP' => ['PA01' => 'K', 'PA03' => 'K'],
        'OFD' => ['PA01' => 'O', 'PA05' => 'K', 'PA06' => 'K', 'PA08' => 'K'],
        'PTN' => ['PA01' => 'O', 'PA03' => 'K', 'PA06' => 'K'],
        'SOC' => ['PA01' => 'O', 'PA03' => 'K', 'PA04' => 'K', 'PA05' => 'K', 'PA15' => 'K'],
        'TTC' => ['PA01' => 'O', 'PA03' => 'K', 'PA04' => 'K', 'PA05' => 'K', 'PA15' => 'K', 'PA16' => 'K'],
        'ECO' => ['PA01' => 'O', 'PA03' => 'K', 'PA05' => 'K', 'PA06' => 'K', 'PA08' => 'K'],
        'CXP' => ['PA01' => 'K', 'PA03' => 'K', 'PA04' => 'K', 'PA15' => 'K', 'PA16' => 'K'],
        'BMK' => ['PA01' => 'K', 'PA03' => 'K', 'PA07' => 'K'],
        'FAT' => ['PA03' => 'O', 'PA06' => 'O', 'PA07' => 'O', 'PA08' => 'O', 'PA09' => 'O', 'PA10' => 'O', 'PA11' => 'O', 'PA12' => 'O', 'PA13' => 'O', 'PA14' => 'O'],
        'LGL' => ['PA01' => 'K', 'PA03' => 'K'],
        'HRG' => ['PA03' => 'K', 'PA04' => 'O', 'PA15' => 'O', 'PA16' => 'O'],
        'ERS' => ['PA03' => 'K', 'PA11' => 'K'],
        'SEC' => ['PA03' => 'K'],
        'DIT' => ['PA03' => 'K', 'PA11' => 'K'],
    ];

    public function run(): void
    {
        $erdigma = Entity::where('code', 'ERDIGMA')->value('id');

        if (! $erdigma) {
            return;
        }

        foreach (self::ERDIGMA as $unit => $peran) {
            foreach ($peran as $pos => $kode) {
                AccountPostRole::withoutGlobalScopes()->firstOrCreate(
                    ['entity_id' => $erdigma, 'unit_code' => $unit, 'post_code' => $pos],
                    ['role' => $kode]
                );
            }
        }
    }
}
