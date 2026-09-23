<?php

namespace App\Support\Bsc;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Ringkasan kinerja SATU entitas untuk tampilan holding.
 *
 * Inilah satu-satunya bentuk data yang menyeberang batas entitas: skor tingkat 1–2,
 * revenue kumulatif, hitungan KPI/sasaran, 19 rasio, dan ringkasan per unit kerja.
 * Data mentah (pos akun, isi sasaran, program kerja) TIDAK pernah ikut, sehingga
 * entitas lain — bahkan holding — tidak dapat membaca transaksinya.
 *
 * Bentuk yang sama dipakai tiga sumber: entitas di database yang sama (lokal),
 * entitas di database terpisah, dan entitas di server lain lewat API.
 */
class EntitySummary
{
    public const SUMBER_LOKAL = 'lokal';

    public const SUMBER_DATABASE = 'database';

    public const SUMBER_API = 'api';

    public const STATUS_OK = 'ok';

    public const STATUS_GALAT = 'galat';

    /**
     * @param  array<int, array<string, mixed>>  $ratios  19 rasio: code·name·unit·target·actual·achievement·status
     * @param  array<int, array<string, mixed>>  $units  ringkasan unit kerja: code·name·objectives·score
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $legalName,
        public readonly string $industry,
        public readonly string $period,
        public readonly ?float $f1,
        public readonly ?float $f2,
        public readonly ?float $apex,
        public readonly float $revenueTarget,
        public readonly float $revenueActual,
        public readonly int $objectives,
        public readonly ?float $objectiveScore,
        public readonly int $kpiTotal,
        public readonly int $kpiApproved,
        public readonly array $ratios = [],
        public readonly array $units = [],
        public readonly string $source = self::SUMBER_LOKAL,
        public readonly string $status = self::STATUS_OK,
        public readonly ?string $message = null,
        public readonly ?string $fetchedAt = null,
    ) {}

    /** Entitas yang tidak dapat dihubungi: skornya kosong, bukan nol. */
    public static function galat(string $code, string $name, string $period, string $source, string $message): self
    {
        return new self(
            code: $code, name: $name, legalName: $name, industry: '', period: $period,
            f1: null, f2: null, apex: null, revenueTarget: 0.0, revenueActual: 0.0,
            objectives: 0, objectiveScore: null, kpiTotal: 0, kpiApproved: 0,
            source: $source, status: self::STATUS_GALAT, message: $message, fetchedAt: now()->toIso8601String(),
        );
    }

    public function ok(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'legal_name' => $this->legalName,
            'industry' => $this->industry,
            'period' => $this->period,
            'f1' => $this->f1,
            'f2' => $this->f2,
            'apex' => $this->apex,
            'revenue_target' => $this->revenueTarget,
            'revenue_actual' => $this->revenueActual,
            'objectives' => $this->objectives,
            'objective_score' => $this->objectiveScore,
            'kpi_total' => $this->kpiTotal,
            'kpi_approved' => $this->kpiApproved,
            'ratios' => $this->ratios,
            'units' => $this->units,
            'source' => $this->source,
            'status' => $this->status,
            'message' => $this->message,
            'fetched_at' => $this->fetchedAt,
        ];
    }

    /**
     * Bentuk ini datang dari SERVER LAIN. Isinya tidak boleh dipercaya begitu
     * saja: satu angka yang ternyata teks, satu baris rasio tanpa 'name', atau
     * satu tanggal yang tidak masuk akal cukup untuk merobohkan halaman
     * Konsolidasi bagi SEMUA pengguna holding — bukan hanya baris entitas itu.
     * Karena itu tiap nilai dipangkas ke bentuk yang memang dipakai.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $source = null): self
    {
        $angka = fn (string $kunci) => self::angka($data[$kunci] ?? null);

        return new self(
            code: self::teks($data['code'] ?? null),
            name: self::teks($data['name'] ?? $data['code'] ?? null),
            legalName: self::teks($data['legal_name'] ?? $data['name'] ?? null),
            industry: self::teks($data['industry'] ?? null),
            period: self::teks($data['period'] ?? null),
            f1: $angka('f1'),
            f2: $angka('f2'),
            apex: $angka('apex'),
            revenueTarget: $angka('revenue_target') ?? 0.0,
            revenueActual: $angka('revenue_actual') ?? 0.0,
            objectives: (int) ($angka('objectives') ?? 0),
            objectiveScore: $angka('objective_score'),
            kpiTotal: (int) ($angka('kpi_total') ?? 0),
            kpiApproved: (int) ($angka('kpi_approved') ?? 0),
            ratios: self::bersihkanBaris($data['ratios'] ?? null, [
                'code' => 'teks', 'name' => 'teks', 'category' => 'teks', 'unit' => 'teks',
                'target' => 'angka', 'actual' => 'angka', 'achievement' => 'angka', 'status' => 'teks',
            ]),
            units: self::bersihkanBaris($data['units'] ?? null, [
                'code' => 'teks', 'name' => 'teks', 'objectives' => 'bulat', 'score' => 'angka', 'status' => 'teks',
            ]),
            source: $source ?? self::teks($data['source'] ?? null, self::SUMBER_LOKAL),
            status: self::teks($data['status'] ?? null, self::STATUS_OK),
            message: isset($data['message']) && is_scalar($data['message']) ? (string) $data['message'] : null,
            fetchedAt: self::waktu($data['fetched_at'] ?? null),
        );
    }

    private static function teks(mixed $nilai, string $bawaan = ''): string
    {
        return is_scalar($nilai) && (string) $nilai !== '' ? (string) $nilai : $bawaan;
    }

    private static function angka(mixed $nilai): ?float
    {
        return is_numeric($nilai) ? (float) $nilai : null;
    }

    /** Waktu pengambilan data; yang tidak terbaca dianggap "sekarang". */
    private static function waktu(mixed $nilai): string
    {
        if (! is_string($nilai) || $nilai === '') {
            return now()->toIso8601String();
        }

        try {
            return Carbon::parse($nilai)->toIso8601String();
        } catch (Throwable) {
            return now()->toIso8601String();
        }
    }

    /**
     * Pangkas daftar baris (rasio / unit kerja) ke kolom yang memang dipakai,
     * dengan jenis yang memang diharapkan. Baris yang bukan array dibuang.
     *
     * @param  array<string, string>  $bentuk  nama kolom => teks|angka|bulat
     * @return array<int, array<string, mixed>>
     */
    private static function bersihkanBaris(mixed $daftar, array $bentuk): array
    {
        if (! is_array($daftar)) {
            return [];
        }

        $bersih = [];

        foreach ($daftar as $baris) {
            if (! is_array($baris)) {
                continue;
            }

            $rapi = [];

            foreach ($bentuk as $kolom => $jenis) {
                $nilai = $baris[$kolom] ?? null;

                $rapi[$kolom] = match ($jenis) {
                    'angka' => self::angka($nilai),
                    'bulat' => (int) (self::angka($nilai) ?? 0),
                    default => self::teks($nilai),
                };
            }

            $bersih[] = $rapi;
        }

        return $bersih;
    }
}
