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

    public function actionPlans()
    {
        return $this->hasMany(ActionPlan::class);
    }
}
