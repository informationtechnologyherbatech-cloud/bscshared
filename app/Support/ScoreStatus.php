<?php

namespace App\Support;

/**
 * Kosakata status pencapaian: satu sumber untuk ambang batas, label, dan warna.
 *
 * Titik status pada piramida dan keterangan di bawahnya dibangun dari daftar
 * yang sama, sehingga warna pada piramida tidak mungkin berbeda arti dengan
 * keterangannya.
 */
class ScoreStatus
{
    public const TERCAPAI = 'tercapai';

    public const WASPADA = 'waspada';

    public const DI_BAWAH = 'di-bawah';

    /** Tingkat yang belum punya data sama sekali — bukan berarti nilainya nol. */
    public const BELUM_LENGKAP = 'belum-lengkap';

    /**
     * Seluruh status beserta label dan warnanya, urut dari terbaik.
     *
     * @return array<int, array{key: string, label: string, color: string}>
     */
    public static function legend(): array
    {
        return [
            ['key' => self::TERCAPAI, 'label' => 'Tercapai 100%', 'color' => '#16a34a'],
            ['key' => self::WASPADA, 'label' => 'Waspada 80–99%', 'color' => '#f59e0b'],
            ['key' => self::DI_BAWAH, 'label' => 'Di bawah target <80%', 'color' => '#dc2626'],
            ['key' => self::BELUM_LENGKAP, 'label' => 'Tidak diskor / data belum lengkap', 'color' => '#94a3b8'],
        ];
    }

    /**
     * Status untuk sebuah capaian.
     *
     * $adaData bernilai false ketika tingkat tersebut belum punya data; skor 0
     * karena tidak ada data berbeda artinya dengan skor 0 karena benar-benar
     * tidak tercapai, dan keduanya tidak boleh ditampilkan sama.
     */
    public static function for(?float $score, bool $adaData = true): string
    {
        if (! $adaData || $score === null) {
            return self::BELUM_LENGKAP;
        }

        if ($score >= 100) {
            return self::TERCAPAI;
        }

        if ($score >= 80) {
            return self::WASPADA;
        }

        return self::DI_BAWAH;
    }

    public static function color(string $key): string
    {
        foreach (self::legend() as $item) {
            if ($item['key'] === $key) {
                return $item['color'];
            }
        }

        return '#94a3b8';
    }

    public static function label(string $key): string
    {
        foreach (self::legend() as $item) {
            if ($item['key'] === $key) {
                return $item['label'];
            }
        }

        return '';
    }
}
