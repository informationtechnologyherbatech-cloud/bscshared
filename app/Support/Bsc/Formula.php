<?php

namespace App\Support\Bsc;

use InvalidArgumentException;

/**
 * Mesin rumus rasio — satu-satunya tempat rumus dihitung.
 *
 * Rumus ditulis memakai kode pos akun, angka, dan operator aritmetika biasa:
 *
 *     (PA01 - PA02) / PA01 * 100
 *     365 / (PA02 / PA05)
 *
 * Kode yang dikenal: seluruh pos akun entitas (PA01, PA02, …, termasuk pos
 * tambahan), dua turunan LK (laba kotor) & LB (laba bersih), serta kode rasio
 * lain — sehingga "365 ÷ Inventory Turnover" cukup ditulis `365 / A1`.
 *
 * Aturan nilai kosong SENGAJA sama dengan perhitungan lama: bila salah satu
 * angka pembentuknya belum diisi, atau penyebutnya nol, hasilnya kosong (null)
 * — bukan nol. Rasio yang kosong tidak ikut diskor, bukan dinilai buruk.
 */
final class Formula
{
    /** Batas kedalaman rujukan antarrasio, mencegah rumus yang saling menunjuk. */
    private const KEDALAMAN_MAKS = 20;

    /**
     * Samakan penulisan sebelum diurai: operator yang biasa disalin dari Excel
     * atau diketik di layar (× ÷ − –) dan koma desimal gaya Indonesia.
     */
    public static function normalise(string $rumus): string
    {
        $rumus = str_replace(['×', '✕', '⋅', '·'], '*', $rumus);
        $rumus = str_replace(['÷', ':'], '/', $rumus);
        $rumus = str_replace(['−', '–', '—'], '-', $rumus);
        $rumus = preg_replace('/(\d),(\d)/', '$1.$2', $rumus); // 0,5 → 0.5

        return trim(preg_replace('/\s+/', ' ', $rumus));
    }

    /**
     * Pecah rumus menjadi token. Pengenal selalu huruf besar supaya PA01 dan
     * pa01 dianggap sama.
     *
     * @return array<int, array{0: string, 1: string}> [jenis, isi]
     */
    public static function tokens(string $rumus): array
    {
        $rumus = self::normalise($rumus);
        $token = [];
        $i = 0;
        $n = strlen($rumus);

        while ($i < $n) {
            $c = $rumus[$i];

            if ($c === ' ') {
                $i++;

                continue;
            }

            if (str_contains('+-*/()', $c)) {
                $token[] = [$c === '(' || $c === ')' ? $c : 'op', $c];
                $i++;

                continue;
            }

            if (ctype_digit($c) || $c === '.') {
                $angka = '';
                while ($i < $n && (ctype_digit($rumus[$i]) || $rumus[$i] === '.')) {
                    $angka .= $rumus[$i++];
                }
                if (! is_numeric($angka)) {
                    throw new InvalidArgumentException('Angka tidak dikenali: "'.$angka.'".');
                }
                $token[] = ['angka', $angka];

                continue;
            }

            if (ctype_alpha($c) || $c === '_') {
                $kode = '';
                while ($i < $n && (ctype_alnum($rumus[$i]) || $rumus[$i] === '_')) {
                    $kode .= $rumus[$i++];
                }
                $token[] = ['kode', strtoupper($kode)];

                continue;
            }

            throw new InvalidArgumentException('Tanda "'.$c.'" tidak dapat dipakai dalam rumus.');
        }

        return $token;
    }

    /**
     * Urai menjadi pohon: ['angka', float] · ['kode', string] ·
     * ['op', '+|-|*|/', kiri, kanan] · ['negatif', simpul].
     *
     * @return array<mixed>
     */
    public static function parse(string $rumus): array
    {
        $token = self::tokens($rumus);

        if ($token === []) {
            throw new InvalidArgumentException('Rumus masih kosong.');
        }

        $posisi = 0;
        $pohon = self::uraiJumlah($token, $posisi);

        if ($posisi < count($token)) {
            throw new InvalidArgumentException('Ada bagian yang tidak dapat dibaca mulai dari "'.$token[$posisi][1].'".');
        }

        return $pohon;
    }

    /** jumlah := kali (('+'|'-') kali)* */
    private static function uraiJumlah(array $token, int &$i): array
    {
        $kiri = self::uraiKali($token, $i);

        while ($i < count($token) && $token[$i][0] === 'op' && ($token[$i][1] === '+' || $token[$i][1] === '-')) {
            $operator = $token[$i++][1];
            $kiri = ['op', $operator, $kiri, self::uraiKali($token, $i)];
        }

        return $kiri;
    }

    /** kali := satuan (('*'|'/') satuan)* */
    private static function uraiKali(array $token, int &$i): array
    {
        $kiri = self::uraiSatuan($token, $i);

        while ($i < count($token) && $token[$i][0] === 'op' && ($token[$i][1] === '*' || $token[$i][1] === '/')) {
            $operator = $token[$i++][1];
            $kiri = ['op', $operator, $kiri, self::uraiSatuan($token, $i)];
        }

        return $kiri;
    }

    /** satuan := '-' satuan | '(' jumlah ')' | angka | kode */
    private static function uraiSatuan(array $token, int &$i): array
    {
        if ($i >= count($token)) {
            throw new InvalidArgumentException('Rumus terputus — masih ada operator tanpa angka sesudahnya.');
        }

        [$jenis, $isi] = $token[$i];

        if ($jenis === 'op' && ($isi === '-' || $isi === '+')) {
            $i++;
            $simpul = self::uraiSatuan($token, $i);

            return $isi === '-' ? ['negatif', $simpul] : $simpul;
        }

        if ($jenis === '(') {
            $i++;
            $simpul = self::uraiJumlah($token, $i);

            if ($i >= count($token) || $token[$i][0] !== ')') {
                throw new InvalidArgumentException('Ada kurung buka yang belum ditutup.');
            }
            $i++;

            return $simpul;
        }

        if ($jenis === ')') {
            throw new InvalidArgumentException('Ada kurung tutup tanpa kurung buka.');
        }

        $i++;

        return $jenis === 'angka' ? ['angka', (float) $isi] : ['kode', $isi];
    }

    /**
     * Seluruh kode yang dipakai rumus, sesuai urutan kemunculannya.
     *
     * @return array<int, string>
     */
    public static function identifiers(string $rumus): array
    {
        $kode = [];
        self::telusuri(self::parse($rumus), function (array $simpul) use (&$kode) {
            if ($simpul[0] === 'kode') {
                $kode[] = $simpul[1];
            }
        });

        return array_values(array_unique($kode));
    }

    /**
     * Peran tiap kode: 'Y' bila berada di penyebut (di kanan tanda bagi dalam
     * jumlah ganjil), selain itu 'P' (pembilang). Dipakai peta pos akun.
     *
     * @return array<string, string>
     */
    public static function roles(string $rumus): array
    {
        $peran = [];

        $jalan = function (array $simpul, bool $diPenyebut) use (&$jalan, &$peran): void {
            switch ($simpul[0]) {
                case 'kode':
                    $baru = $diPenyebut ? 'Y' : 'P';
                    $peran[$simpul[1]] = isset($peran[$simpul[1]]) && $peran[$simpul[1]] !== $baru
                        ? 'P,Y'
                        : ($peran[$simpul[1]] ?? $baru);
                    break;
                case 'negatif':
                    $jalan($simpul[1], $diPenyebut);
                    break;
                case 'op':
                    $jalan($simpul[2], $diPenyebut);
                    $jalan($simpul[3], $simpul[1] === '/' ? ! $diPenyebut : $diPenyebut);
                    break;
            }
        };

        $jalan(self::parse($rumus), false);

        return $peran;
    }

    /**
     * Hitung rumus dari nilai pos akun yang sudah "dipakai".
     *
     * @param  array<string, float|null>  $nilai
     * @param  (callable(string): (float|null))|null  $rujukan  penyelesai kode di luar $nilai (mis. rasio lain)
     */
    public static function evaluate(string $rumus, array $nilai, ?callable $rujukan = null): ?float
    {
        return self::hitung(self::parse($rumus), $nilai, $rujukan);
    }

    /**
     * @param  array<string, float|null>  $nilai
     */
    private static function hitung(array $simpul, array $nilai, ?callable $rujukan): ?float
    {
        switch ($simpul[0]) {
            case 'angka':
                return $simpul[1];

            case 'kode':
                $kode = $simpul[1];

                if (array_key_exists($kode, $nilai)) {
                    return $nilai[$kode] === null ? null : (float) $nilai[$kode];
                }

                return $rujukan ? $rujukan($kode) : null;

            case 'negatif':
                $x = self::hitung($simpul[1], $nilai, $rujukan);

                return $x === null ? null : -$x;

            case 'op':
                $a = self::hitung($simpul[2], $nilai, $rujukan);
                $b = self::hitung($simpul[3], $nilai, $rujukan);

                if ($a === null || $b === null) {
                    return null;
                }

                return match ($simpul[1]) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    // Penyebut nol: hasilnya kosong, bukan tak hingga.
                    '/' => $b == 0.0 ? null : $a / $b,
                };
        }

        return null;
    }

    /**
     * Periksa rumus sebelum disimpan. Mengembalikan keterangan galat dalam
     * bahasa Indonesia, atau null bila rumusnya sah.
     *
     * @param  array<int, string>  $kodeDikenal
     */
    public static function validate(string $rumus, array $kodeDikenal): ?string
    {
        if (trim($rumus) === '') {
            return 'Rumus belum diisi.';
        }

        try {
            $dipakai = self::identifiers($rumus);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        $asing = array_values(array_diff($dipakai, $kodeDikenal));

        if ($asing !== []) {
            return 'Kode tidak dikenal: '.implode(', ', $asing).'. Pakai kode pos akun yang tersedia.';
        }

        if ($dipakai === []) {
            return 'Rumus harus memakai paling sedikit satu pos akun.';
        }

        return null;
    }

    /**
     * Rumus yang angkanya sudah disubstitusi — dipakai menampilkan
     * "cara menghitungnya" di layar, sehingga pengguna tidak perlu kalkulator.
     *
     * @param  array<string, float|null>  $nilai
     * @param  (callable(string): string)|null  $tulis  cara menulis satu angka
     */
    public static function substitute(string $rumus, array $nilai, ?callable $tulis = null): string
    {
        $tulis ??= fn (?float $x) => $x === null ? '—' : number_format($x, 0, ',', '.');

        return self::tulisUlang($rumus, fn (string $kode) => $tulis($nilai[$kode] ?? null));
    }

    /**
     * Rumus yang kodenya diganti nama pos akun, untuk dibaca manusia.
     *
     * @param  array<string, string>  $nama
     */
    public static function humanise(string $rumus, array $nama): string
    {
        return self::tulisUlang($rumus, fn (string $kode) => $nama[$kode] ?? $kode);
    }

    /**
     * Tulis ulang rumus dengan tiap kode diganti sesuatu yang lain.
     *
     * Kurung hanya dipasang bila memang mengubah arti — "Laba kotor ÷ Penjualan
     * × 100" lebih mudah dibaca daripada "(Laba kotor ÷ Penjualan) × 100",
     * sedangkan "(Aset lancar − Persediaan) ÷ Liabilitas lancar" kurungnya wajib.
     *
     * @param  callable(string): string  $ganti
     */
    private static function tulisUlang(string $rumus, callable $ganti): string
    {
        $tingkat = fn (array $simpul): int => $simpul[0] !== 'op' ? 3 : (in_array($simpul[1], ['+', '-'], true) ? 1 : 2);

        $jalan = function (array $simpul) use (&$jalan, $ganti, $tingkat): string {
            switch ($simpul[0]) {
                case 'angka':
                    return rtrim(rtrim(number_format($simpul[1], 4, ',', '.'), '0'), ',');
                case 'kode':
                    return $ganti($simpul[1]);
                case 'negatif':
                    return '−'.$jalan($simpul[1]);
                case 'op':
                    $sendiri = $tingkat($simpul);
                    $kiri = $jalan($simpul[2]);
                    $kanan = $jalan($simpul[3]);

                    if ($tingkat($simpul[2]) < $sendiri) {
                        $kiri = '('.$kiri.')';
                    }

                    // Kanan: selain kurang kuat, "a − (b − c)" dan "a ÷ (b ÷ c)"
                    // juga butuh kurung walau tingkatnya sama.
                    if ($tingkat($simpul[3]) < $sendiri
                        || ($tingkat($simpul[3]) === $sendiri && in_array($simpul[1], ['-', '/'], true))) {
                        $kanan = '('.$kanan.')';
                    }

                    return $kiri.' '.self::tanda($simpul[1]).' '.$kanan;
            }

            return '';
        };

        return $jalan(self::parse($rumus));
    }

    private static function tanda(string $operator): string
    {
        return match ($operator) {
            '*' => '×',
            '/' => '÷',
            '-' => '−',
            default => '+',
        };
    }

    private static function kurungSeimbang(string $teks): bool
    {
        $dalam = 0;

        for ($i = 0; $i < strlen($teks); $i++) {
            $dalam += $teks[$i] === '(' ? 1 : ($teks[$i] === ')' ? -1 : 0);

            if ($dalam < 0) {
                return false;
            }
        }

        return $dalam === 0;
    }

    /** @param  callable(array): void  $kunjungi */
    private static function telusuri(array $simpul, callable $kunjungi): void
    {
        $kunjungi($simpul);

        if ($simpul[0] === 'negatif') {
            self::telusuri($simpul[1], $kunjungi);
        }

        if ($simpul[0] === 'op') {
            self::telusuri($simpul[2], $kunjungi);
            self::telusuri($simpul[3], $kunjungi);
        }
    }

    /**
     * Penyelesai rujukan antarrasio: kode rasio lain dihitung lebih dulu,
     * dengan pengaman agar rumus yang saling menunjuk tidak berputar selamanya.
     *
     * @param  array<string, string>  $rumusRasio  kode rasio → rumusnya
     * @param  array<string, float|null>  $nilai
     */
    public static function resolver(array $rumusRasio, array $nilai, array $sedangDihitung = []): callable
    {
        return function (string $kode) use ($rumusRasio, $nilai, $sedangDihitung): ?float {
            if (! isset($rumusRasio[$kode])
                || in_array($kode, $sedangDihitung, true)
                || count($sedangDihitung) >= self::KEDALAMAN_MAKS) {
                return null;
            }

            $sedangDihitung[] = $kode;

            try {
                return self::evaluate($rumusRasio[$kode], $nilai, self::resolver($rumusRasio, $nilai, $sedangDihitung));
            } catch (InvalidArgumentException) {
                return null;
            }
        };
    }
}
