<?php

namespace App\Console\Commands;

use App\Models\OdooConnection;
use App\Models\Period;
use App\Support\Bsc\Integration\OdooPuller;
use App\Support\EntityContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tarik angka keuangan dari Odoo ke Pos Akun.
 *
 * Dijadwalkan harian (lihat routes/console.php) dan dapat dijalankan tangan
 * bila sedang tutup buku. Tiap sambungan dikerjakan di dalam konteks entitas
 * pemiliknya, sehingga baris yang ditulis benar-benar tertanda entitas itu —
 * pada konsol tidak ada pengguna yang login, jadi pembatas entitas tidak
 * menyala dengan sendirinya.
 */
class TarikOdoo extends Command
{
    protected $signature = 'bsc:tarik-odoo
        {--period= : Periode YYYY-MM; bawaannya periode berjalan tiap entitas}
        {--entity= : Kode entitas tertentu, mis. AEJ}';

    protected $description = 'Tarik pos akun & realisasi revenue dari Odoo tiap entitas';

    public function handle(OdooPuller $puller, EntityContext $konteks): int
    {
        $sambungan = OdooConnection::with('entity')->where('is_active', true)->get();

        if ($kode = $this->option('entity')) {
            $sambungan = $sambungan->filter(fn ($s) => strtoupper((string) $s->entity?->code) === strtoupper($kode));
        }

        if ($sambungan->isEmpty()) {
            $this->warn('Tidak ada sambungan Odoo yang aktif.');

            return self::SUCCESS;
        }

        $gagal = 0;

        foreach ($sambungan as $satu) {
            $nama = $satu->entity?->name ?? 'entitas #'.$satu->entity_id;

            try {
                $hasil = $konteks->runAs($satu->entity_id, function () use ($puller, $satu) {
                    return $puller->pull($satu, $this->option('period') ?: Period::currentPeriod());
                });

                $satu->forceFill([
                    'last_status' => $hasil->ok() ? 'ok' : 'galat',
                    'last_message' => mb_substr($hasil->message, 0, 500),
                    'last_run_at' => now(),
                ])->save();

                $hasil->ok()
                    ? $this->info($nama.': '.$hasil->message)
                    : $this->warn($nama.': '.$hasil->message);

                if (! $hasil->ok()) {
                    $gagal++;
                }
            } catch (Throwable $e) {
                $gagal++;
                report($e);
                Log::warning('Tarikan Odoo '.$nama.' gagal: '.$e->getMessage());

                $satu->forceFill([
                    'last_status' => 'galat',
                    'last_message' => mb_substr($e->getMessage(), 0, 500),
                    'last_run_at' => now(),
                ])->save();

                $this->error($nama.': '.$e->getMessage());
            }
        }

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
