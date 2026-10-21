<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialRatio extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'category',
        'ratio_name',
        'target',
        'actual',
        'achievement_pct',
        'status',
    ];

    public function getUnitAttribute(): string
    {
        $name = strtoupper($this->ratio_name);
        if (str_contains($name, 'MARGIN') || str_contains($name, 'ROA') || str_contains($name, 'ROE') || str_contains($name, '(%)') || $this->category === 'Profitabilitas') {
            return '%';
        }
        if (str_contains($name, 'OUTSTANDING') || str_contains($name, 'DSO') || str_contains($name, 'DIO') || str_contains($name, 'DPO') || str_contains($name, 'HARI')) {
            return ' Hari';
        }
        return 'x';
    }

    public function getBenchmarkDisplayAttribute(): string
    {
        $name = strtoupper(trim($this->ratio_name));
        $unit = $this->unit;

        if (str_contains($name, 'DPO') || str_contains($name, 'DAYS PAYABLE')) {
            return '60 - 90 Hari';
        }
        if (str_contains($name, 'DSO') || str_contains($name, 'DIO') || str_contains($name, 'DER') || str_contains($name, 'DAR')) {
            return '≤ ' . number_format($this->target, 2) . $unit;
        }
        return '≥ ' . number_format($this->target, 2) . $unit;
    }
}
