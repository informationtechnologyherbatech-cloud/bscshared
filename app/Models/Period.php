<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    use BelongsToEntity, HasFactory;

    protected $fillable = ['entity_id', 'period', 'status', 'apex_score'];

    public function isClosed(): bool
    {
        return $this->status === 'CLOSED';
    }
}
