<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StagingLog extends Model
{
    use BelongsToEntity, HasFactory;

    protected $fillable = [
        'entity_id',
        'period',
        'dept_code',
        'idempotency_key',
        'status',
        'source_version',
        'message',
    ];
}
