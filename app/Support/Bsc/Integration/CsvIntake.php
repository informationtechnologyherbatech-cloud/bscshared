<?php

namespace App\Support\Bsc\Integration;

use App\Models\Period;
use RuntimeException;

/**
 * Pembaca berkas CSV untuk pemasukan data.
 *
 * Satu pengunggah melayani dua bentuk berkas, dikenali dari judul kolomnya:
 *
 *   1. SALDO AKUN  — kode akun buku besar, untuk Pos Akun & rasio keuangan:
 *        kode,nilai,saldo_awal[,periode]
 *        4-10001,812000000,,2026-08
 *
 *   2. REALISASI KPI — sesuai format baku panduan adaptasi:
 *        periode,dept_code,kpi_code,target,actual[,idempotency_key]
 *
 * Nama kolom boleh Indonesia maupun Inggris, huruf besar/kecil bebas. Kolom
 * `periode` boleh dikosongkan bila periodenya sudah dipilih di layar.
 *
 * Pemisahnya dikenali sendiri: berkas dari Excel berbahasa Indonesia lazimnya
 * memakai titik koma, bukan koma.
 */
class CsvIntake
{
    /** @var array<string, array<int, string>> nama kolom baku => sebutan yang diterima */
    private const KOLOM = [
        'code' => ['kode', 'kode_akun', 'akun', 'account', 'account_code', 'code'],
        'amount' => ['nilai', 'saldo', 'jumlah', 'amount', 'balance', 'value'],
        'opening' => ['saldo_awal', 'awal', 'opening', 'opening_balance'],
        'period' => ['periode', 'period'],
        'dept_code' => ['dept_code', 'departemen', 'dept', 'unit', 'unit_code'],
        'kpi_code' => ['kpi_code', 'kode_kpi', 'kpi'],
        'target' => ['target'],
        'actual' => ['actual', 'realisasi'],
        'idempotency_key' => ['idempotency_key', 'penanda'],
    ];

    public function __construct(
        private AccountIntake $akun,
        private ObjectiveIntake $objektif,
    ) {}

    /**
     * @param  string  $path  berkas CSV yang sudah tersimpan di cakram
     * @param  string|null  $period  periode dari layar; kolom `periode` di berkas menang
     */
    public function apply(string $path, ?string $period = null): IntakeResult
    {
        [$judul, $baris] = $this->baca($path);

        if ($baris === []) {
            return new IntakeResult(IntakeResult::DITOLAK, (string) $period, 'Berkasnya tidak berisi satu baris data pun.');
        }

        $periodeBerkas = $this->nilai($judul, $baris[0], 'period');
        $periode = $periodeBerkas ?: ($period ?: Period::currentPeriod());

        if ($this->punya($judul, 'kpi_code')) {
            return $this->objektif->apply($periode, array_map(fn ($b) => [
                'dept_code' => (string) $this->nilai($judul, $b, 'dept_code'),
                'kpi_code' => (string) $this->nilai($judul, $b, 'kpi_code'),
                'target' => $this->nilai($judul, $b, 'target'),
                'actual' => $this->nilai($judul, $b, 'actual'),
            ], $baris), [
                'source' => 'Unggahan CSV',
                'idempotency_key' => $this->nilai($judul, $baris[0], 'idempotency_key')
                    ?: 'IDEMP-CSV-KPI-'.now()->format('Ymd-His-v'),
            ]);
        }

        if (! $this->punya($judul, 'code') || ! $this->punya($judul, 'amount')) {
            return new IntakeResult(IntakeResult::DITOLAK, $periode,
                'Judul kolom tidak dikenali. Pakai "kode,nilai[,saldo_awal]" untuk saldo akun, '
                .'atau "periode,dept_code,kpi_code,target,actual" untuk realisasi KPI.');
        }

        return $this->akun->apply($periode, array_map(fn ($b) => [
            'code' => (string) $this->nilai($judul, $b, 'code'),
            'amount' => $this->angka($this->nilai($judul, $b, 'amount')),
            'opening' => $this->angka($this->nilai($judul, $b, 'opening')),
        ], $baris), [
            'source' => 'Unggahan CSV',
            'dept_code' => 'FIN',
            'idempotency_key' => $this->nilai($judul, $baris[0], 'idempotency_key')
                ?: 'IDEMP-CSV-AKUN-'.now()->format('Ymd-His-v'),
        ]);
    }

    /**
     * @return array{0: array<string, int>, 1: array<int, array<int, string>>}
     */
    private function baca(string $path): array
    {
        $isi = @file_get_contents($path);

        if ($isi === false) {
            throw new RuntimeException('Berkasnya tidak dapat dibaca.');
        }

        // Excel menulis BOM di depan berkas UTF-8; tanpa dibuang, judul kolom
        // pertama tidak akan pernah cocok.
        $isi = preg_replace('/^\x{FEFF}/u', '', $isi) ?? $isi;
        $barisTeks = preg_split('/\r\n|\r|\n/', trim($isi)) ?: [];

        if ($barisTeks === [] || trim($barisTeks[0]) === '') {
            return [[], []];
        }

        $pemisah = substr_count($barisTeks[0], ';') > substr_count($barisTeks[0], ',') ? ';' : ',';
        $judul = [];

        foreach (str_getcsv($barisTeks[0], $pemisah) as $i => $nama) {
            $judul[$this->bakukan((string) $nama)] = $i;
        }

        $baris = [];

        foreach (array_slice($barisTeks, 1) as $satu) {
            if (trim($satu) === '') {
                continue;
            }

            $baris[] = str_getcsv($satu, $pemisah);
        }

        return [$judul, $baris];
    }

    /** @param  array<string, int>  $judul */
    private function punya(array $judul, string $kolom): bool
    {
        return $this->indeks($judul, $kolom) !== null;
    }

    /**
     * @param  array<string, int>  $judul
     * @param  array<int, string>  $baris
     */
    private function nilai(array $judul, array $baris, string $kolom): ?string
    {
        $i = $this->indeks($judul, $kolom);
        $nilai = $i === null ? null : trim((string) ($baris[$i] ?? ''));

        return ($nilai ?? '') === '' ? null : $nilai;
    }

    /** @param  array<string, int>  $judul */
    private function indeks(array $judul, string $kolom): ?int
    {
        foreach (self::KOLOM[$kolom] as $sebutan) {
            if (isset($judul[$sebutan])) {
                return $judul[$sebutan];
            }
        }

        return null;
    }

    private function bakukan(string $nama): string
    {
        return strtolower(trim(str_replace([' ', '-'], '_', trim($nama, "\"' \t"))));
    }

    /** Angka bergaya Indonesia (1.234.567,89) maupun Inggris (1,234,567.89). */
    private function angka(?string $nilai): ?float
    {
        if ($nilai === null) {
            return null;
        }

        $bersih = preg_replace('/[^0-9,.\-]/', '', $nilai) ?? '';

        if ($bersih === '' || $bersih === '-') {
            return null;
        }

        $koma = strrpos($bersih, ',');
        $titik = strrpos($bersih, '.');

        // Pemisah desimalnya adalah tanda yang muncul PALING BELAKANG.
        if ($koma !== false && ($titik === false || $koma > $titik)) {
            $bersih = str_replace(['.', ','], ['', '.'], $bersih);
        } else {
            $bersih = str_replace(',', '', $bersih);
        }

        return is_numeric($bersih) ? (float) $bersih : null;
    }
}
