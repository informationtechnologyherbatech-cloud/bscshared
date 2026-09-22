<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepartmentObjective extends Model
{
    use BelongsToEntity, HasFactory;

    protected $fillable = [
        'entity_id',
        'kpi_cascade_id',
        'period',
        'dept_code',
        'kpi_code',
        'kpi_name',
        'polarity',
        'target',
        'actual',
        'achievement_pct',
        'status',
    ];

    /** KPI cascade (L3) asal sasaran ini; kosong untuk sasaran lama/manual. */
    public function kpiCascade()
    {
        return $this->belongsTo(KpiCascade::class);
    }

    public function actionPlans()
    {
        return $this->hasMany(ActionPlan::class);
    }
}
