<?php

namespace App\Support\Bsc\Integration;

use App\Models\AccountMapping;
use App\Models\OdooConnection;
use App\Support\Bsc\AccountPosts;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Menarik angka satu periode dari Odoo dan memasukkannya ke Pos Akun.
 *
 * Yang rumit di sini bukan pemanggilannya, melainkan ARTI angkanya. Katalog pos
 * akun membedakan dua jenis, dan keduanya butuh rentang tanggal yang berbeda:
 *
 *   - pos ALIRAN (penjualan, HPP, beban) diisi nilai YTD — mutasi 1 Januari
 *     sampai akhir periode, karena rumus rasio menyetahunkannya ×12 ÷ n;
 *   - pos NERACA (kas, piutang, utang, ekuitas) diisi SALDO, bukan mutasi:
 *     akumulasi sejak awal buku sampai akhir periode. Saldo awal tahunnya
 *     ditarik terpisah (akumulasi sampai 31 Desember tahun sebelumnya) supaya
 *     rasio dapat memakai rata-rata saldo awal & akhir.
 *
 * Realisasi revenue bulanan ditarik sendiri sebagai mutasi BULAN ITU SAJA —
 * berbeda dengan PA01 yang YTD — karena Tingkat 1 menjumlahkannya sendiri dari
 * Januari.
 */
class OdooPuller
{
    /** Tanggal paling awal yang dianggap "sejak awal buku". */
    private const AWAL_BUKU = '1900-01-01';

    public function __construct(private AccountIntake $intake) {}

    public function pull(OdooConnection $sambungan, string $period, ?OdooClient $klien = null): IntakeResult
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new RuntimeException('Periode harus berbentuk YYYY-MM.');
        }

        $klien ??= OdooClient::for($sambungan);

        $akhir = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth();
        $awalTahun = $akhir->copy()->startOfYear();
        $akhirTahunLalu = $awalTahun->copy()->subDay();

        // Tiga jendela waktu, tiga arti berbeda.
        $ytd = $klien->balances($awalTahun->toDateString(), $akhir->toDateString());
        $saldoAkhir = $klien->balances(self::AWAL_BUKU, $akhir->toDateString());
        $saldoAwal = $klien->balances(self::AWAL_BUKU, $akhirTahunLalu->toDateString());

        $jenisPos = $this->jenisPerKodeAkun();
        $baris = [];

        foreach ($jenisPos as $kodeAkun => $jenis) {
            $neraca = $jenis === AccountPosts::NERACA;
            $nilai = $neraca ? ($saldoAkhir[$kodeAkun] ?? null) : ($ytd[$kodeAkun] ?? null);

            if ($nilai === null) {
                continue;   // akun tanpa mutasi sama sekali
            }

            $baris[] = [
                'code' => $kodeAkun,
                'amount' => $nilai,
                'opening' => $neraca ? ($saldoAwal[$kodeAkun] ?? 0.0) : null,
            ];
        }

        $revenue = $sambungan->fills_revenue
            ? $this->revenueBulanIni($klien, $akhir)
            : null;

        return $this->intake->apply($period, $baris, [
            'source' => 'Odoo',
            'dept_code' => 'FIN',
            'idempotency_key' => 'IDEMP-ODOO-'.strtoupper((string) $sambungan->entity?->code).'-'.$period.'-'.now()->format('Ymd-His'),
            'revenue' => $revenue,
        ]);
    }

    /**
     * Jenis pos untuk tiap kode akun yang sudah dipetakan entitas ini.
     *
     * @return array<string, string> kode akun => aliran|neraca|…
     */
    private function jenisPerKodeAkun(): array
    {
        $katalog = AccountPosts::all();
        $hasil = [];

        foreach (AccountMapping::get() as $petak) {
            if (isset($katalog[$petak->post_code])) {
                $hasil[strtoupper($petak->source_code)] = $katalog[$petak->post_code]['kind'];
            }
        }

        return $hasil;
    }

    /**
     * Realisasi revenue bulan berjalan: mutasi akun-akun yang dipetakan ke PA01
     * selama bulan itu saja. Tandanya dibalik bila pemetaannya menyatakan
     * demikian, sehingga angkanya positif seperti yang dibaca manusia.
     */
    private function revenueBulanIni(OdooClient $klien, Carbon $akhir): ?float
    {
        $penjualan = AccountMapping::where('post_code', 'PA01')->get();

        if ($penjualan->isEmpty()) {
            return null;
        }

        $mutasi = $klien->balances($akhir->copy()->startOfMonth()->toDateString(), $akhir->toDateString());
        $jumlah = 0.0;
        $ada = false;

        foreach ($penjualan as $petak) {
            $kode = strtoupper($petak->source_code);

            if (! array_key_exists($kode, $mutasi)) {
                continue;
            }

            $ada = true;
            $jumlah += ($petak->invert ? -1 : 1) * $mutasi[$kode];
        }

        return $ada ? $jumlah : null;
    }
}
