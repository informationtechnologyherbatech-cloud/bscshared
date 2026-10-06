<?php

namespace App\Support\Bsc;

use App\Models\AccountPostDefinition;
use App\Support\EntityContext;

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

    /** Katalog entitas aktif dalam permintaan ini; dikosongkan lewat forget(). */
    private static array $cache = [];

    /**
     * Pos akun yang dipakai perhitungan: katalog entitas aktif, hanya yang
     * masih aktif. Bila entitas belum punya katalog sendiri (konsol, seeder,
     * pengujian), dipakai 16 pos bawaan workbook.
     *
     * @return array<string, array{name: string, kind: string, source: string, hint: string}>
     */
    public static function all(): array
    {
        return array_filter(self::catalog(), fn (array $pos) => $pos['active'] ?? true);
    }

    /**
     * Seluruh pos akun entitas termasuk yang dinonaktifkan — dipakai layar
     * pengelolaan dan saat mencari nama pos lama.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalog(): array
    {
        $entitas = app(EntityContext::class)->id();

        if ($entitas === null) {
            return self::builtins();
        }

        if (array_key_exists($entitas, self::$cache)) {
            return self::$cache[$entitas];
        }

        try {
            $baris = AccountPostDefinition::orderBy('sort')->orderBy('code')->get();
        } catch (\Throwable) {
            // Tabelnya belum ada (migrasi sedang berjalan) — pakai bawaan.
            return self::builtins();
        }

        if ($baris->isEmpty()) {
            return self::$cache[$entitas] = self::builtins();
        }

        $katalog = [];

        foreach ($baris as $pos) {
            $katalog[$pos->code] = [
                'name' => $pos->name,
                'kind' => $pos->kind,
                'source' => $pos->source,
                'hint' => (string) $pos->hint,
                'active' => $pos->is_active,
                'builtin' => $pos->is_builtin,
                'sort' => $pos->sort,
            ];
        }

        return self::$cache[$entitas] = $katalog;
    }

    /** Lupakan katalog yang sudah dibaca — dipanggil sesudah katalog diubah. */
    public static function forget(): void
    {
        self::$cache = [];
    }

    /**
     * 16 pos akun bawaan workbook. Inilah susunan awal setiap entitas, dan
     * acuan bila sebuah entitas belum punya katalognya sendiri.
     *
     * @return array<string, array{name: string, kind: string, source: string, hint: string}>
     */
    public static function builtins(): array
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
        // Katalog penuh: pos yang dinonaktifkan pun masih punya jenis, supaya
        // angka lamanya tetap terbaca benar.
        return self::catalog()[$code]['kind'] ?? self::builtins()[$code]['kind'] ?? self::ALIRAN;
    }

    /** Nama pos akun untuk ditampilkan; kode yang tidak dikenal tampil apa adanya. */
    public static function nameOf(string $code): string
    {
        return self::catalog()[$code]['name'] ?? self::builtins()[$code]['name'] ?? $code;
    }

    /**
     * Jenis pos beserta keterangan cara angkanya dipakai — dipakai pilihan
     * jenis saat menambah pos akun.
     *
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return [
            self::ALIRAN => 'Aliran — diisi nilai YTD, lalu disetahunkan ×12 ÷ bulan berjalan',
            self::NERACA => 'Neraca — diisi saldo awal tahun & saldo akhir, lalu dirata-rata',
            self::HRIS_RATA => 'Rata-rata periode — dipakai apa adanya (mis. jumlah karyawan)',
            self::HRIS_ALIRAN => 'Aliran HRIS — diisi total YTD, lalu disetahunkan (mis. jam kerja)',
        ];
    }

    /**
     * Sistem asal angka pos akun. Singkatannya dipakai di tabel, keterangannya
     * dipakai saat memilih — "GL" sendirian tidak berarti apa-apa bagi pengisi.
     *
     * @return array<string, string>
     */
    public static function sources(): array
    {
        return [
            'GL' => 'GL — Buku besar akuntansi (General Ledger), lewat Odoo atau unggahan CSV',
            'HRIS' => 'HRIS — Sistem kepegawaian (jumlah karyawan, jam kerja)',
            'GL / HRIS' => 'GL / HRIS — Dapat berasal dari keduanya',
            'Manual' => 'Manual — Diketik sendiri di layar Pos Akun, tanpa sistem sumber',
        ];
    }

    /** Keterangan panjang sebuah sumber; sumber di luar daftar tampil apa adanya. */
    public static function sourceLabel(string $source): string
    {
        return self::sources()[$source] ?? $source;
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
     * Isian pos akun diubah dari dasar KUMULATIF menjadi dasar SATU BULAN.
     *
     * Dipakai Finance entitas untuk memvalidasi satu bulan: pertanyaannya bukan
     * "sampai Agustus sudah sampai mana?" melainkan "angka Agustus sendiri sudah
     * betul atau belum?". Angka itulah yang dapat dibandingkan dengan laporan
     * bulanan mereka sendiri.
     *
     * Pos aliran disimpan sebagai nilai YTD, jadi angka satu bulan adalah
     * selisihnya terhadap bulan sebelumnya. Pos neraca sudah berupa saldo akhir
     * tiap bulan, sehingga rata-rata bulanannya memakai saldo akhir bulan lalu
     * sebagai saldo awal. Januari tidak perlu bulan pembanding: YTD Januari
     * memang bulan Januari.
     *
     * Dua hal sengaja dilaporkan, bukan ditutupi:
     *   - needs_previous : bulan sebelumnya belum diisi, sehingga selisihnya tidak
     *     dapat dihitung. Memakai YTD apa adanya akan diam-diam kembali ke dasar
     *     kumulatif — justru dasar yang sedang diperiksa.
     *   - negative : YTD-nya menyusut (bulan ini lebih kecil dari bulan lalu).
     *     Bisa benar (pembalikan jurnal), bisa salah input; yang jelas harus
     *     dilihat manusia sebelum angkanya dipercaya.
     *
     * @param  array<string, array{amount: float|null, opening: float|null}>  $sekarang
     * @param  array<string, array{amount: float|null, opening: float|null}>  $sebelumnya  isian bulan sebelumnya; kosong bila belum ada
     * @return array{
     *     inputs: array<string, array{amount: float|null, opening: float|null}>,
     *     needs_previous: array<int, string>,
     *     negative: array<int, string>
     * }
     */
    public static function monthBasis(array $sekarang, array $sebelumnya, bool $januari): array
    {
        $inputs = [];
        $butuhSebelumnya = [];
        $menyusut = [];

        foreach (self::all() as $kode => $pos) {
            $nilai = $sekarang[$kode]['amount'] ?? null;
            $bulanLalu = $sebelumnya[$kode]['amount'] ?? null;

            if ($januari) {
                $inputs[$kode] = [
                    'amount' => $nilai,
                    'opening' => $sekarang[$kode]['opening'] ?? null,
                ];

                continue;
            }

            $inputs[$kode] = match ($pos['kind']) {
                self::ALIRAN, self::HRIS_ALIRAN => (function () use ($kode, $nilai, $bulanLalu, &$butuhSebelumnya, &$menyusut) {
                    if ($nilai === null) {
                        return ['amount' => null, 'opening' => null];
                    }

                    if ($bulanLalu === null) {
                        $butuhSebelumnya[] = $kode;

                        return ['amount' => null, 'opening' => null];
                    }

                    $selisih = $nilai - $bulanLalu;

                    if ($selisih < 0) {
                        $menyusut[] = $kode;
                    }

                    return ['amount' => $selisih, 'opening' => null];
                })(),
                // Saldo akhir bulan lalu adalah saldo awal bulan ini.
                self::NERACA => ['amount' => $nilai, 'opening' => $bulanLalu],
                // Sudah berupa rata-rata periode; dipakai apa adanya.
                default => ['amount' => $nilai, 'opening' => null],
            };
        }

        return [
            'inputs' => $inputs,
            'needs_previous' => $butuhSebelumnya,
            'negative' => $menyusut,
        ];
    }

    /**
     * Bagaimana SATU pos akun berubah dari isian di layar menjadi "nilai dipakai".
     *
     * Dipakai dua layar: kolom "Nilai dipakai" di menu Pos Akun, dan penelusuran
     * rasio di Piramida — supaya pertanyaan "angka ini asalnya dari mana?"
     * terjawab di tempat angkanya muncul, bukan harus dihitung sendiri.
     *
     * Aturannya SENGAJA menirukan usedValues() baris demi baris; keduanya dijaga
     * tetap sama oleh pengujian yang membandingkan hasil keduanya untuk seluruh
     * 16 pos.
     *
     * @return array{label: string, arithmetic: string, value: float|null}
     */
    public static function derivation(string $code, ?float $amount, ?float $opening, int $bulanBerjalan, bool $bulananSaja = false): array
    {
        $jenis = self::kind($code);
        $n = max(1, min(12, $bulanBerjalan));
        $angka = fn (?float $x) => $x === null ? '—' : number_format($x, 0, ',', '.');

        if ($amount === null) {
            return ['label' => 'belum diisi', 'arithmetic' => '', 'value' => null];
        }

        return match ($jenis) {
            // Pada dasar satu bulan, angka yang masuk sudah berupa angka bulan itu
            // (selisih YTD), jadi ceritanya pun bukan lagi "YTD ÷ n bulan".
            self::ALIRAN, self::HRIS_ALIRAN => $bulananSaja
                ? [
                    'label' => 'bulan ini × 12',
                    'arithmetic' => $angka($amount).' × 12',
                    'value' => $amount * 12,
                ]
                : [
                    'label' => 'YTD × 12 ÷ '.$n,
                    'arithmetic' => $angka($amount).' × 12 ÷ '.$n.' bulan',
                    'value' => $amount * 12 / $n,
                ],
            self::NERACA => $opening === null
                ? [
                    'label' => 'saldo akhir',
                    'arithmetic' => $angka($amount).($bulananSaja ? ' (saldo akhir bulan lalu belum ada)' : ' (saldo awal tahun belum diisi)'),
                    'value' => $amount,
                ]
                : [
                    'label' => $bulananSaja ? 'rata-rata bulan lalu & kini' : 'rata-rata awal & akhir',
                    'arithmetic' => '('.$angka($opening).' + '.$angka($amount).') ÷ 2',
                    'value' => ($amount + $opening) / 2,
                ],
            default => [
                'label' => 'dipakai apa adanya',
                'arithmetic' => $angka($amount),
                'value' => $amount,
            ],
        };
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
