<?php

namespace App\Support\Bsc\Sources;

use App\Models\Entity;
use App\Support\Bsc\EntitySummary;
use App\Support\Bsc\EntitySummaryBuilder;
use App\Support\EntityContext;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entitas dengan DATABASE SENDIRI di server yang sama. Holding membuka koneksi
 * tambahan ke database itu, membaca ringkasannya, lalu menutupnya kembali —
 * tidak ada data entitas yang disalin ke database holding.
 *
 * Selama membaca, koneksi bawaan dialihkan sementara supaya seluruh model
 * (Scorecard, RatioEngine, dst.) berjalan pada database entitas tersebut tanpa
 * perlu diubah satu per satu.
 */
class DatabaseEntitySource implements EntitySource
{
    public function __construct(
        private EntityContext $context,
        private EntitySummaryBuilder $builder,
        private string $database,
    ) {}

    public function summary(Entity $entitas, string $period): EntitySummary
    {
        $koneksi = 'bsc_entitas_'.strtolower($entitas->code);
        $bawaan = (string) Config::get('database.default');
        $dasar = Config::get('database.connections.'.$bawaan, []);

        Config::set('database.connections.'.$koneksi, array_merge($dasar, [
            'database' => $this->database,
            // Hanya membaca; jangan ikut menjalankan migrasi/antrian di sini.
            'sticky' => false,
        ]));
        DB::purge($koneksi);

        try {
            Config::set('database.default', $koneksi);
            // Id entitas dapat berbeda di tiap database → cari berdasarkan kodenya.
            $context = $this->context;
            $context->flushCache();
            $lokal = Entity::withoutGlobalScopes()->where('code', $entitas->code)->first();

            if (! $lokal) {
                return EntitySummary::galat(
                    $entitas->code, $entitas->name, $period, $this->name(),
                    'Entitas '.$entitas->code.' tidak ada di database '.$this->database.'.'
                );
            }

            return $context->runAs($lokal->id, fn () => $this->builder->forEntity($lokal, $period, $this->name()));
        } catch (Throwable $e) {
            // Rinciannya (host, nama database, SQL) hanya ke log; layar cukup tahu
            // bahwa sumbernya tidak terbaca.
            Log::warning('Database entitas '.$entitas->code.' tidak terbaca: '.$e->getMessage());

            return EntitySummary::galat(
                $entitas->code, $entitas->name, $period, $this->name(),
                'Database entitas tidak dapat dibaca; periksa nama database & kredensial di .env.'
            );
        } finally {
            Config::set('database.default', $bawaan);
            DB::purge($koneksi);
            $this->context->flushCache();
        }
    }

    public function name(): string
    {
        return EntitySummary::SUMBER_DATABASE;
    }
}
