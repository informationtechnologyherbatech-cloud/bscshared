<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialRatio extends Model
{
    use BelongsToEntity, HasFactory;

    protected $fillable = [
        'entity_id',
        'period',
        'category',
        'ratio_name',
        'ratio_code',
        'unit',
        'polarity',
        'target',
        'actual',
        'achievement_pct',
        'weight',
        'rubric_score',
        'weighted_score',
        'status',
        'source',
    ];

    /**
     * Rasio hasil hitungan pos akun tidak diubah langsung — nilainya diturunkan
     * dari pos akun dan target di Katalog Rasio.
     */
    public function isComputed(): bool
    {
        return $this->source === RatioEngine::SOURCE_COMPUTED;
    }

    /** Nilai target/aktual sesuai satuannya (%, x, hari, Rp); data lama tanpa satuan tetap 2 desimal. */
    public function display(mixed $nilai): string
    {
        return $this->unit ? RatioLibrary::format((float) $nilai, $this->unit) : number_format((float) $nilai, 2);
    }
}
