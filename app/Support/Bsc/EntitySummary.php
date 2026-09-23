<?php

namespace App\Support\Bsc;

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

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data, ?string $source = null): self
    {
        $angka = fn (string $kunci) => isset($data[$kunci]) && is_numeric($data[$kunci]) ? (float) $data[$kunci] : null;

        return new self(
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? $data['code'] ?? ''),
            legalName: (string) ($data['legal_name'] ?? $data['name'] ?? ''),
            industry: (string) ($data['industry'] ?? ''),
            period: (string) ($data['period'] ?? ''),
            f1: $angka('f1'),
            f2: $angka('f2'),
            apex: $angka('apex'),
            revenueTarget: (float) ($data['revenue_target'] ?? 0),
            revenueActual: (float) ($data['revenue_actual'] ?? 0),
            objectives: (int) ($data['objectives'] ?? 0),
            objectiveScore: $angka('objective_score'),
            kpiTotal: (int) ($data['kpi_total'] ?? 0),
            kpiApproved: (int) ($data['kpi_approved'] ?? 0),
            ratios: is_array($data['ratios'] ?? null) ? $data['ratios'] : [],
            units: is_array($data['units'] ?? null) ? $data['units'] : [],
            source: $source ?? (string) ($data['source'] ?? self::SUMBER_LOKAL),
            status: (string) ($data['status'] ?? self::STATUS_OK),
            message: $data['message'] ?? null,
            fetchedAt: $data['fetched_at'] ?? now()->toIso8601String(),
        );
    }
}
