<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris sheet "L3 Cascade KPI": KPI tahunan satu jabatan di satu unit.
 *
 * Head memegang Lag, Supervisor Lead, Staff Output. Tiap jabatan Σ bobot 100%.
 * Hanya KPI berstatus Lolos yang dimasukkan ke monitoring bulanan
 * (Objective Departemen).
 */
class KpiCascade extends Model
{
    use BelongsToEntity;

    public const HEAD = 'Head';

    public const SUPERVISOR = 'Supervisor';

    public const STAFF = 'Staff';

    public const LEVELS = [self::HEAD, self::SUPERVISOR, self::STAFF];

    /** Jenis ukuran yang sesuai tiap level (Uji A Q7). */
    public const MEASURE_FOR_LEVEL = [self::HEAD => 'Lag', self::SUPERVISOR => 'Lead', self::STAFF => 'Output'];

    /** Huruf kode KPI tiap level, mengikuti workbook (SCM-H01, SCM-S01, SCM-T01). */
    public const CODE_LETTER = [self::HEAD => 'H', self::SUPERVISOR => 'S', self::STAFF => 'T'];

    public const DRIVER = 'Driver';

    public const GUARDRAIL = 'Guardrail';

    public const BELUM_DIUJI = 'Belum diuji';

    public const LOLOS = 'Lolos';

    public const REVISI = 'Revisi';

    public const STATUSES = [self::BELUM_DIUJI, self::LOLOS, self::REVISI];

    protected $fillable = [
        'entity_id', 'year', 'code', 'unit_code', 'brand', 'level', 'parent_code', 'position',
        'objective', 'measure_type', 'target', 'unit_label', 'polarity', 'reporting_period',
        'method', 'key_initiative', 'work_program', 'record', 'weight', 'kpi_type', 'elasticity',
        'ratio_code', 'post_code', 'direction', 'individual_type', 'validation_status',
        'finance_notes', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'target' => 'float',
            'weight' => 'float',
            'elasticity' => 'float',
            'sort' => 'integer',
        ];
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(DepartmentObjective::class);
    }

    public function isGuardrail(): bool
    {
        return $this->kpi_type === self::GUARDRAIL;
    }

    public function isApproved(): bool
    {
        return $this->validation_status === self::LOLOS;
    }

    /** Level induk yang sah: Supervisor → Head, Staff → Supervisor. */
    public static function parentLevel(string $level): ?string
    {
        return match ($level) {
            self::SUPERVISOR => self::HEAD,
            self::STAFF => self::SUPERVISOR,
            default => null,
        };
    }
}
