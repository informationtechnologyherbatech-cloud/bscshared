<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'department_objective_id',
        'title',
        'owner_dept',
        'pic_name',
        'due_date',
        'priority',
        'budget_estimate',
        'actual_cost',
        'progress_pct',
        'status',
        'notes',
    ];

    public function objective()
    {
        return $this->belongsTo(DepartmentObjective::class, 'department_objective_id');
    }

    public function getBudgetFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->budget_estimate, 0, ',', '.');
    }

    public function getActualCostFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->actual_cost, 0, ',', '.');
    }
}
