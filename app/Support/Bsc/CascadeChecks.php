<?php

namespace App\Support\Bsc;

use App\Models\AccountPostRole;
use App\Models\KpiCascade;
use Illuminate\Support\Collection;

/**
 * Kolom "auto" sheet L3 Cascade KPI dan ringkasan per unit kerja.
 *
 * Pemeriksaan yang dapat dihitung dari data (bagian logis Uji A):
 *   - Σ bobot per jabatan = 100% (Q8)
 *   - jenis ukuran sesuai level: Head=Lag, Supervisor=Lead, Staff=Output (Q7)
 *   - pos akun muncul di rumus rasio yang diklaim (Q3)
 *   - unit adalah Pemilik/Kontributor pos akun itu (Q4)
 *   - kode KPI induk ada, satu level di atas, dan di unit yang sama
 */
class CascadeChecks
{
    private const EPS = 0.01;

    /** @var Collection<int, KpiCascade> */
    private Collection $rows;

    /** @var array<string, float> kunci jabatan => Σ bobot */
    private array $weights = [];

    /** @param  Collection<int, KpiCascade>  $rows  KPI satu tahun */
    public function __construct(Collection $rows, private PostMap $map)
    {
        $this->rows = $rows->values();

        foreach ($this->rows as $r) {
            $kunci = self::positionKey($r);
            $this->weights[$kunci] = ($this->weights[$kunci] ?? 0) + (float) $r->weight;
        }
    }

    /** Jabatan dihitung per unit + brand + nama jabatan, seperti kolom Q workbook. */
    public static function positionKey(KpiCascade $r): string
    {
        return mb_strtolower($r->unit_code.'|'.trim((string) $r->brand).'|'.trim($r->position));
    }

    /**
     * @return array{
     *     weight_sum: float, weight_ok: bool, role: string|null, impact_name: string|null,
     *     post_name: string|null, measure_ok: bool, claim_ok: bool|null, parent_ok: bool,
     *     issues: array<int, string>
     * }
     */
    public function forRow(KpiCascade $r): array
    {
        $masalah = [];
        $jumlah = round($this->weights[self::positionKey($r)] ?? 0, 2);
        $bobotOk = abs($jumlah - 100) < self::EPS;
        if (! $bobotOk) {
            $masalah[] = 'Σ bobot jabatan '.self::angka($jumlah).'% (harus 100%)';
        }

        $ukuranOk = (KpiCascade::MEASURE_FOR_LEVEL[$r->level] ?? null) === $r->measure_type;
        if (! $ukuranOk) {
            $masalah[] = $r->level.' seharusnya memakai ukuran '.(KpiCascade::MEASURE_FOR_LEVEL[$r->level] ?? '—');
        }

        $peran = $r->post_code ? $this->map->roleOf($r->unit_code, $r->post_code) : null;

        // Guardrail tidak dipaksa ke rasio/pos akun (Uji A: cukup Q5, Q7, Q8).
        $klaimOk = null;
        if (! $r->isGuardrail()) {
            if (! $r->ratio_code || ! $r->post_code) {
                $klaimOk = false;
                $masalah[] = 'KPI Driver wajib mengisi rasio dan pos akun yang digerakkan';
            } else {
                $posDiRumus = in_array($r->post_code, RatioLibrary::postsOf($r->ratio_code), true);
                if (! $posDiRumus) {
                    $masalah[] = 'Pos akun '.$r->post_code.' tidak ada di rumus '.$r->ratio_code;
                }
                if ($peran === null) {
                    $masalah[] = 'Unit '.$r->unit_code.' tidak terpetakan pada '.$r->post_code.' — periksa Peta';
                }
                $klaimOk = $posDiRumus && $peran !== null;
            }
        }

        $indukOk = $this->parentOk($r);
        if (! $indukOk) {
            $harus = KpiCascade::parentLevel($r->level);
            $masalah[] = $r->parent_code
                ? 'KPI induk '.$r->parent_code.' tidak ditemukan sebagai '.$harus.' di unit '.$r->unit_code
                : 'KPI '.$r->level.' wajib menunjuk KPI induk ('.$harus.')';
        }

        return [
            'weight_sum' => $jumlah,
            'weight_ok' => $bobotOk,
            'role' => $r->post_code ? AccountPostRole::label($peran) : null,
            'impact_name' => $r->ratio_code ? (RatioLibrary::impactName($r->ratio_code) ?? 'kode tidak dikenal') : null,
            'post_name' => $r->post_code ? (AccountPosts::all()[$r->post_code]['name'] ?? 'kode tidak dikenal') : null,
            'measure_ok' => $ukuranOk,
            'claim_ok' => $klaimOk,
            'parent_ok' => $indukOk,
            'issues' => $masalah,
        ];
    }

    private function parentOk(KpiCascade $r): bool
    {
        $harus = KpiCascade::parentLevel($r->level);

        if ($harus === null) {
            return true;
        }

        return $this->rows->contains(fn ($p) => $p->code === $r->parent_code
            && $p->level === $harus
            && $p->unit_code === $r->unit_code);
    }

    /**
     * Blok "RINGKASAN PER UNIT KERJA".
     *
     * @param  array<int, string>  $units
     * @return array<string, array{head_weight: float, head_check: string, supervisors: int, supervisor_bad: int, staff: int, staff_bad: int, brands: int}>
     */
    public function unitSummary(array $units): array
    {
        $hasil = [];

        foreach ($units as $unit) {
            $baris = $this->rows->where('unit_code', $unit);
            $head = $baris->where('level', KpiCascade::HEAD);
            $bobotHead = round((float) $head->sum('weight'), 2);

            // Unit berbasis brand: Head tiap brand masing-masing 100%.
            $perBrand = $head->groupBy(fn ($r) => trim((string) $r->brand))
                ->map(fn ($g) => round((float) $g->sum('weight'), 2));
            $cek = match (true) {
                $head->isEmpty() => '—',
                $perBrand->every(fn ($w) => abs($w - 100) < self::EPS) => 'OK',
                default => $perBrand->count() > 1 ? '≠100% per brand' : '≠100%',
            };

            $salah = fn (string $level) => $baris->where('level', $level)
                ->filter(fn ($r) => abs(($this->weights[self::positionKey($r)] ?? 0) - 100) >= self::EPS)->count();

            $hasil[$unit] = [
                'head_weight' => $bobotHead,
                'head_check' => $cek,
                'supervisors' => $baris->where('level', KpiCascade::SUPERVISOR)->count(),
                'supervisor_bad' => $salah(KpiCascade::SUPERVISOR),
                'staff' => $baris->where('level', KpiCascade::STAFF)->count(),
                'staff_bad' => $salah(KpiCascade::STAFF),
                'brands' => $baris->pluck('brand')->filter(fn ($b) => trim((string) $b) !== '')->unique()->count(),
            ];
        }

        return $hasil;
    }

    /**
     * Urutan pohon: tiap Head diikuti Supervisor-nya, lalu Staff di bawahnya.
     * KPI yang induknya tidak ditemukan ditaruh di akhir unitnya.
     *
     * @return array<int, array{row: KpiCascade, depth: int}>
     */
    public function tree(): array
    {
        $hasil = [];
        $sudah = [];
        $urut = fn (Collection $c) => $c->sortBy(fn ($r) => sprintf('%05d|%s', $r->sort, $r->code));

        $tambah = function (KpiCascade $r, int $depth) use (&$tambah, &$hasil, &$sudah, $urut) {
            if (isset($sudah[$r->id])) {
                return;
            }
            $sudah[$r->id] = true;
            $hasil[] = ['row' => $r, 'depth' => $depth];

            $anak = $this->rows->filter(fn ($c) => $c->parent_code === $r->code && $c->unit_code === $r->unit_code);
            foreach ($urut($anak) as $c) {
                $tambah($c, $depth + 1);
            }
        };

        foreach ($this->rows->groupBy('unit_code') as $baris) {
            foreach ($urut($baris->where('level', KpiCascade::HEAD)) as $head) {
                $tambah($head, 0);
            }
            foreach ($urut($baris) as $sisa) {
                $tambah($sisa, $sisa->level === KpiCascade::HEAD ? 0 : 1);
            }
        }

        return $hasil;
    }

    private static function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }
}
