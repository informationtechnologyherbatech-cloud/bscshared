<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepartmentObjective extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'dept_code',
        'kpi_code',
        'kpi_name',
        'source_type',
        'odoo_account_code',
        'impact_note',
        'polarity',
        'unit',
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
