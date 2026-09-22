<?php

namespace App\Support\Bsc;

/**
 * Katalog 16 pos akun — sheet "Asumsi" bagian F pada
 * Cascading_Revenue_Rasio_KPI_Erdigma_2026.xlsx.
 *
 * Katalog ini sengaja ditulis sebagai kode, bukan data: 19 rumus rasio
 * (lihat RatioLibrary) disusun dari pos-pos ini, sehingga menambah atau
 * mengubah pos akun berarti mengubah rumusnya juga.
 *
 * Jenis pos menentukan cara nilai "dipakai" (bagian G):
 *   - ALIRAN : diisi nilai YTD, lalu disetahunkan ×12 ÷ bulan berjalan;
 *   - NERACA : diisi saldo awal tahun & saldo akhir periode, lalu dirata-rata;
 *   - HRIS_RATA : diisi rata-rata periode (jumlah karyawan), dipakai apa adanya;
 *   - HRIS_ALIRAN : diisi total YTD (jam kerja), lalu disetahunkan.
 */
class AccountPosts
{
    public const ALIRAN = 'aliran';

    public const NERACA = 'neraca';

    public const HRIS_RATA = 'hris_rata';

    public const HRIS_ALIRAN = 'hris_aliran';

    /**
     * @return array<string, array{name: string, kind: string, source: string, hint: string}>
     */
    public static function all(): array
    {
        return [
            'PA01' => ['name' => 'Penjualan', 'kind' => self::ALIRAN, 'source' => 'GL', 'hint' => 'Penjualan bersih setelah diskon & retur.'],
            'PA02' => ['name' => 'HPP', 'kind' => self::ALIRAN, 'source' => 'GL', 'hint' => 'Harga pokok produk yang benar-benar terjual.'],
            'PA03' => ['name' => 'Beban usaha & lain-lain (termasuk bunga, pajak)', 'kind' => self::ALIRAN, 'source' => 'GL', 'hint' => 'Seluruh biaya di luar HPP: iklan, komisi, gaji non-produksi, sewa, bunga, pajak.'],
            'PA04' => ['name' => 'Beban tenaga kerja', 'kind' => self::ALIRAN, 'source' => 'GL / HRIS', 'hint' => 'Gaji, tunjangan, lembur, BPJS.'],
            'PA05' => ['name' => 'Persediaan', 'kind' => self::NERACA, 'source' => 'GL', 'hint' => 'Barang di gudang yang belum terjual.'],
            'PA06' => ['name' => 'Piutang usaha', 'kind' => self::NERACA, 'source' => 'GL', 'hint' => 'Tagihan ke pelanggan yang belum dibayar.'],
            'PA07' => ['name' => 'Utang usaha', 'kind' => self::NERACA, 'source' => 'GL', 'hint' => 'Tagihan pemasok yang belum dibayar.'],
            'PA08' => ['name' => 'Kas & setara kas', 'kind' => self::NERACA, 'source' => 'GL', 'hint' => 'Rekening bank & deposito jangka pendek.'],
            'PA09' => ['name' => 'Aset lancar', 'kind' => self::NERACA, 'source' => 'GL', 'hint' => 'Kas + piutang + persediaan + uang muka.'],
            'PA10' => ['name' => 'Liabilitas lancar', 'kind' => self::NERACA, 'source' => 'GL', 'hint' => 'Kewajiban yang jatuh tempo ≤ 1 tahun.'],
            'PA11' => ['name' => 'Total aset', 'kind' => self::NERACA, 'source' => 'GL', 'hint' => 'Aset lancar + aset tetap.'],
            'PA12' => ['name' => 'Total liabilitas', 'kind' => self::NERACA, 'source' => 'GL', 'hint' => 'Kewajiban jangka pendek + jangka panjang.'],
            'PA13' => ['name' => 'Ekuitas', 'kind' => self::NERACA, 'source' => 'GL', 'hint' => 'Modal disetor + laba ditahan.'],
            'PA14' => ['name' => 'Modal (disetor)', 'kind' => self::NERACA, 'source' => 'GL', 'hint' => 'Modal yang disetor pemegang saham, tanpa laba ditahan.'],
            'PA15' => ['name' => 'Jumlah karyawan (rata-rata)', 'kind' => self::HRIS_RATA, 'source' => 'HRIS', 'hint' => 'Rata-rata jumlah karyawan dalam periode.'],
            'PA16' => ['name' => 'Total jam kerja', 'kind' => self::HRIS_ALIRAN, 'source' => 'HRIS', 'hint' => 'Total jam kerja YTD, termasuk lembur.'],
        ];
    }

    public static function kind(string $code): string
    {
        return self::all()[$code]['kind'];
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            self::ALIRAN => 'Aliran',
            self::NERACA => 'Neraca',
            self::HRIS_RATA => 'HRIS',
            self::HRIS_ALIRAN => 'HRIS',
        };
    }

    /** Pos neraca butuh saldo awal & akhir; selainnya satu angka. */
    public static function needsOpening(string $code): bool
    {
        return self::kind($code) === self::NERACA;
    }

    /**
     * Nilai yang dipakai rumus rasio (bagian G kolom "Nilai dipakai"),
     * ditambah Laba kotor (LK) dan Laba bersih (LB) turunannya.
     *
     * @param  array<string, array{amount: float|null, opening: float|null}>  $masukan
     * @param  int  $bulanBerjalan  n pada konvensi ×12 ÷ n
     * @return array<string, float|null> null = belum diisi
     */
    public static function usedValues(array $masukan, int $bulanBerjalan): array
    {
        $n = max(1, min(12, $bulanBerjalan));
        $dipakai = [];

        foreach (self::all() as $kode => $pos) {
            $nilai = $masukan[$kode]['amount'] ?? null;

            if ($nilai === null) {
                $dipakai[$kode] = null;

                continue;
            }

            $dipakai[$kode] = match ($pos['kind']) {
                self::ALIRAN, self::HRIS_ALIRAN => $nilai * 12 / $n,
                self::NERACA => ($masukan[$kode]['opening'] ?? null) === null
                    ? $nilai // tanpa saldo awal: pakai saldo akhir
                    : ($nilai + $masukan[$kode]['opening']) / 2,
                self::HRIS_RATA => $nilai,
            };
        }

        return self::withDerived($dipakai);
    }

    /**
     * Tambahkan/hitung ulang turunan Laba kotor (LK = PA01 − PA02) dan Laba
     * bersih (LB = LK − PA03) dari nilai dipakai.
     *
     * @param  array<string, float|null>  $dipakai
     * @return array<string, float|null>
     */
    public static function withDerived(array $dipakai): array
    {
        $dipakai['LK'] = self::selisih($dipakai['PA01'] ?? null, $dipakai['PA02'] ?? null);
        $dipakai['LB'] = self::selisih($dipakai['LK'], $dipakai['PA03'] ?? null);

        return $dipakai;
    }

    private static function selisih(?float $a, ?float $b): ?float
    {
        return $a === null || $b === null ? null : $a - $b;
    }
}
