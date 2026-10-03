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
        // Periode milik program kerja itu sendiri; kaitan ke sasaran mutu opsional,
        // sehingga periode tidak boleh bergantung padanya.
        'period',
        'title',
        'owner_dept',
        'progress_pct',
        'status',
    ];

    /**
     * Tiap program kerja selalu punya periode.
     *
     * Kaitan ke sasaran mutu bersifat opsional, jadi periode tidak boleh
     * bergantung padanya: program kerja tanpa periode tidak akan pernah terbaca
     * Tingkat 4 piramida walau sudah tampil di menunya. Aturannya dipasang di
     * model agar berlaku untuk semua jalan masuk — layar, seeder, maupun impor.
     */
    protected static function booted(): void
    {
        static::creating(function (self $rencana) {
            if ($rencana->period) {
                return;
            }

            $rencana->period = $rencana->department_objective_id
                ? DepartmentObjective::whereKey($rencana->department_objective_id)->value('period')
                : null;

            $rencana->period ??= Period::currentPeriod();
        });
    }

    public function objective()
    {
        return $this->belongsTo(DepartmentObjective::class, 'department_objective_id');
    }
}
