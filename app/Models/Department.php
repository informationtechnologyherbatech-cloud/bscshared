<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'code',
        'name',
        'manager_title',
        'description',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function objectives()
    {
        return $this->hasMany(DepartmentObjective::class, 'dept_code', 'code');
    }

    public function actionPlans()
    {
        return $this->hasMany(ActionPlan::class, 'owner_dept', 'code');
    }
}
