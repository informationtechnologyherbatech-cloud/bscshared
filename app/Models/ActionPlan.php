<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActionPlan extends Model
{
    use BelongsToEntity, HasFactory;

    protected $fillable = [
        'entity_id',
        'department_objective_id',
        'title',
        'owner_dept',
        'progress_pct',
        'status',
    ];

    public function objective()
    {
        return $this->belongsTo(DepartmentObjective::class, 'department_objective_id');
    }
}
