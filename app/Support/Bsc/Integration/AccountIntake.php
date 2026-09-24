<?php

namespace App\Support\Bsc\Integration;

use App\Models\AccountBalance;
use App\Models\AccountMapping;
use App\Models\Period;
use App\Models\RevenueTarget;
use App\Models\StagingLog;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\RatioEngine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalan masuk data keuangan dari luar ke Pos Akun.
 *
 * Unggahan berkas dan tarikan dari Odoo bermuara di sini, sehingga aturan yang
 * berlaku persis sama bagi keduanya: periode tertutup ditolak, kode akun
 * diterjemahkan lewat pemetaan entitas, nilainya dijumlahkan per pos, rasio
 * dihitung ulang, dan semuanya meninggalkan satu baris jejak audit.
 *
 * Kunci idempotensi membuat kiriman yang sama — mis. penjadwal berjalan dua
 * kali, atau pengguna menekan tombol dua kali — tidak menimpa data untuk kedua
 * kalinya.
 *
 * Arti nilai mengikuti katalog pos akun:
 *   - pos ALIRAN (penjualan, HPP, beban) diisi nilai YTD Januari s.d. periode;
 *   - pos NERACA diisi saldo akhir periode, dan saldo awal tahun bila ada.
 * Penarik dari Odoo-lah yang bertanggung jawab menyiapkan angka itu.
 */
class AccountIntake
{
    public function __construct(private RatioEngine $ratios) {}

    /**
     * @param  array<int, array{code: string, amount: mixed, opening?: mixed, name?: string}>  $rows
     * @param  array{source?: string, dept_code?: string, idempotency_key?: string, revenue?: float|null}  $opsi
     */
    public function apply(string $period, array $rows, array $opsi = []): IntakeResult
    {
        $sumber = $opsi['source'] ?? 'Integrasi';
        $kunci = $opsi['idempotency_key'] ?? 'IDEMP-'.strtoupper(substr($sumber, 0, 6)).'-'.now()->format('Ymd-His-v');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return new IntakeResult(IntakeResult::DITOLAK, $period, 'Periode harus berbentuk YYYY-MM.');
        }

        if (Period::where('period', $period)->first()?->isClosed()) {
            return new IntakeResult(IntakeResult::DITOLAK, $period,
                'Periode '.$period.' sudah ditutup; datanya tidak diubah.');
        }

        // Kiriman yang sama tidak diproses dua kali. Diperiksa lebih dulu agar
        // pesannya ramah; benteng sebenarnya adalah indeks unik saat menyimpan.
        if (StagingLog::where('idempotency_key', $kunci)->exists()) {
            return new IntakeResult(IntakeResult::GANDA, $period,
                'Kiriman dengan penanda '.$kunci.' sudah pernah diproses; tidak ada yang diubah.');
        }

        [$pos, $saldoAwal, $belumDipetakan, $bermasalah] = $this->terjemahkan($rows);

        if ($pos === []) {
            return new IntakeResult(IntakeResult::DITOLAK, $period,
                'Tidak ada satu pun kode akun yang dikenali. Lengkapi Pemetaan Akun lebih dulu.',
                unmapped: $belumDipetakan, problems: $bermasalah);
        }

        $revenue = isset($opsi['revenue']) && is_numeric($opsi['revenue']) ? (float) $opsi['revenue'] : null;
        $jumlahRasio = 0;

        try {
            DB::transaction(function () use ($period, $pos, $saldoAwal, $revenue, $kunci, $sumber, $opsi, $belumDipetakan, &$jumlahRasio) {
                foreach ($pos as $kode => $nilai) {
                    $nilaiBaru = ['amount' => $nilai];

                    // Saldo awal hanya bermakna bagi pos neraca; pos lain
                    // mengabaikannya supaya tidak ada angka menggantung.
                    if (AccountPosts::needsOpening($kode) && array_key_exists($kode, $saldoAwal)) {
                        $nilaiBaru['opening'] = $saldoAwal[$kode];
                    }

                    AccountBalance::updateOrCreate(['period' => $period, 'code' => $kode], $nilaiBaru);
                }

                if ($revenue !== null) {
                    // Target revenue TIDAK disentuh: itu hasil perencanaan, bukan
                    // hasil pencatatan. Yang masuk dari luar hanya realisasinya.
                    RevenueTarget::updateOrCreate(['period' => $period], ['actual' => $revenue]);
                }

                $hasil = $this->ratios->materialize($period);
                $jumlahRasio = count(array_filter(
                    $hasil['rows'] ?? [],
                    fn ($r) => ($r['actual'] ?? null) !== null
                ));

                StagingLog::create([
                    'period' => $period,
                    'dept_code' => strtoupper($opsi['dept_code'] ?? 'FIN'),
                    'idempotency_key' => $kunci,
                    'status' => 'SCORED',
                    'source_version' => 1,
                    'message' => $this->ringkasan($sumber, $pos, $revenue, $belumDipetakan),
                ]);
            });
        } catch (QueryException $e) {
            // Indeks unik (entitas, penanda) menolak kiriman kembar yang datang
            // bersamaan — pemeriksaan di atas tidak menutup celah balapan.
            if (StagingLog::where('idempotency_key', $kunci)->exists()) {
                return new IntakeResult(IntakeResult::GANDA, $period,
                    'Kiriman dengan penanda '.$kunci.' sudah pernah diproses; tidak ada yang diubah.');
            }

            throw $e;
        }

        return new IntakeResult(
            IntakeResult::DITERIMA, $period,
            $this->ringkasan($sumber, $pos, $revenue, $belumDipetakan),
            posts: $pos, unmapped: $belumDipetakan, problems: $bermasalah,
            revenue: $revenue, ratios: $jumlahRasio,
        );
    }

    /**
     * Kode akun sumber → pos akun BSC. Beberapa akun boleh menunjuk pos yang
     * sama; nilainya dijumlahkan.
     *
     * @param  array<int, array{code: string, amount: mixed, opening?: mixed}>  $rows
     * @return array{0: array<string, float>, 1: array<string, float>, 2: array<int, string>, 3: array<int, string>}
     */
    private function terjemahkan(array $rows): array
    {
        $pemetaan = AccountMapping::get()->keyBy(fn ($m) => strtoupper($m->source_code));
        $dikenal = AccountPosts::all();

        $pos = [];
        $saldoAwal = [];
        $belumDipetakan = [];
        $bermasalah = [];

        foreach ($rows as $baris) {
            $kode = strtoupper(trim((string) ($baris['code'] ?? '')));

            if ($kode === '') {
                continue;
            }

            if (! is_numeric($baris['amount'] ?? null)) {
                $bermasalah[] = $kode.': nilainya bukan angka';

                continue;
            }

            // Kode PA dipakai apa adanya: pengirimnya sudah menerjemahkan sendiri.
            if (isset($dikenal[$kode])) {
                $tujuan = $kode;
                $balik = false;
            } elseif ($petak = $pemetaan->get($kode)) {
                $tujuan = $petak->post_code;
                $balik = (bool) $petak->invert;
            } else {
                $belumDipetakan[] = $kode;

                continue;
            }

            if (! isset($dikenal[$tujuan])) {
                $bermasalah[] = $kode.': dipetakan ke pos tidak dikenal '.$tujuan;

                continue;
            }

            $tanda = $balik ? -1 : 1;
            $pos[$tujuan] = ($pos[$tujuan] ?? 0.0) + $tanda * (float) $baris['amount'];

            if (is_numeric($baris['opening'] ?? null)) {
                $saldoAwal[$tujuan] = ($saldoAwal[$tujuan] ?? 0.0) + $tanda * (float) $baris['opening'];
            }
        }

        return [$pos, $saldoAwal, array_values(array_unique($belumDipetakan)), $bermasalah];
    }

    /**
     * @param  array<string, float>  $pos
     * @param  array<int, string>  $belumDipetakan
     */
    private function ringkasan(string $sumber, array $pos, ?float $revenue, array $belumDipetakan): string
    {
        $bagian = [$sumber.': '.count($pos).' pos akun diperbarui ('.implode(', ', array_keys($pos)).')'];

        if ($revenue !== null) {
            $bagian[] = 'realisasi revenue '.rupiah($revenue);
        }

        if ($belumDipetakan !== []) {
            $bagian[] = count($belumDipetakan).' kode akun belum dipetakan ('
                .implode(', ', array_slice($belumDipetakan, 0, 5)).(count($belumDipetakan) > 5 ? ', …' : '').')';
        }

        $bagian[] = 'rasio keuangan dihitung ulang';

        return implode('; ', $bagian).'.';
    }
}
