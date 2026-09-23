<?php

namespace App\Support\Bsc;

use App\Models\Entity;
use App\Models\IntercompanySale;
use App\Support\Bsc\Sources\EntitySourceFactory;
use App\Support\Bsc\Sources\EntitySourceSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Tampilan holding atas keempat entitas: input tiap entitas boleh berbeda,
 * tetapi keluarannya seragam — F1, F2, dan skor puncak berskala 0–100.
 *
 * Revenue grup = Σ revenue entitas − penjualan antarentitas (eliminasi), agar
 * produk Herbatech yang dijual lewat Erdigma tidak terhitung dua kali.
 *
 *   F1 grup = (Σ realisasi − eliminasi realisasi) ÷ (Σ target − eliminasi rencana),
 *             kumulatif Jan s.d. periode, maks 100.
 *   F2 grup = rata-rata F2 entitas, dibobot target revenue YTD masing-masing
 *             (rasio laporan konsolidasi butuh pos akun konsolidasi, yang belum
 *             dicatat; pembobotan revenue membuat entitas besar berpengaruh besar).
 *   Skor puncak grup = 0,45 × F1 grup + 0,55 × F2 grup.
 *
 * Isolasi data: angka tiap entitas TIDAK dibaca dari database holding, melainkan
 * diminta ke sumbernya (EntitySourceFactory) — database entitas atau API entitas.
 * Yang menyeberang hanya EntitySummary (skor, revenue kumulatif, 19 rasio, ringkasan
 * unit kerja); transaksi dan isi sasaran tetap tinggal di entitas masing-masing.
 * Hasilnya disimpan sebentar di cache (config bsc.consolidation_ttl) dan dapat
 * disegarkan lewat tombol Segarkan.
 */
class Consolidation
{
    public function __construct(private EntitySourceFactory $sources) {}

    /**
     * Kunci cache ringkasan satu entitas. Ikut memuat identitas pemasangan dan
     * sidik sumbernya, sehingga dua pemasangan yang berbagi cache tidak saling
     * menimpa dan pergantian sumber (API ↔ database) tidak dilayani data lama.
     */
    public static function cacheKey(Entity $entitas, string $period): string
    {
        $sumber = app(EntitySourceSettings::class)->for($entitas);
        $sidik = substr(sha1(json_encode([
            config('bsc.default_entity'),
            config('app.url'),
            $sumber['driver'],
            $sumber['api_url'],
            $sumber['database'],
        ])), 0, 10);

        return 'bsc:ringkasan:'.$sidik.':'.$entitas->code.':'.$period;
    }

    /** Buang ringkasan tersimpan agar panggilan berikutnya menarik data baru. */
    public function refresh(string $period): void
    {
        foreach (Entity::active()->get() as $entitas) {
            Cache::forget(self::cacheKey($entitas, $period));
        }
    }

    /**
     * @return array{
     *     entities: array<int, array<string, mixed>>,
     *     group: array<string, mixed>,
     *     eliminations: Collection<int, IntercompanySale>
     * }
     */
    public function forPeriod(string $period): array
    {
        $tahun = substr($period, 0, 4);
        $baris = [];

        foreach (Entity::active()->get() as $entitas) {
            $ringkasan = $this->summary($entitas, $period);

            $baris[] = [
                'entity' => $entitas,
                'revenue_target' => $ringkasan->revenueTarget,
                'revenue_actual' => $ringkasan->revenueActual,
                'f1' => $ringkasan->f1,
                'f2' => $ringkasan->f2,
                'apex' => $ringkasan->apex,
                'objectives' => $ringkasan->objectives,
                'objective_score' => $ringkasan->objectiveScore,
                'kpi_total' => $ringkasan->kpiTotal,
                'kpi_approved' => $ringkasan->kpiApproved,
                // Telusur terbatas: 19 rasio & ringkasan unit kerja, tanpa data mentah.
                'ratios' => $ringkasan->ratios,
                'units' => $ringkasan->units,
                'source' => $ringkasan->source,
                'source_label' => $this->sources->describe($entitas),
                'status' => $ringkasan->status,
                'message' => $ringkasan->message,
                'fetched_at' => $ringkasan->fetchedAt,
            ];
        }

        $eliminasi = IntercompanySale::with(['seller', 'buyer'])
            ->where('period', '>=', $tahun.'-01')->where('period', '<=', $period)
            ->orderBy('period')->get();

        $targetKotor = array_sum(array_column($baris, 'revenue_target'));
        $realisasiKotor = array_sum(array_column($baris, 'revenue_actual'));
        $elimRencana = (float) $eliminasi->sum(fn ($e) => (float) ($e->planned_amount ?? 0));
        $elimRealisasi = (float) $eliminasi->sum(fn ($e) => (float) ($e->actual_amount ?? 0));
        $targetBersih = $targetKotor - $elimRencana;
        $realisasiBersih = $realisasiKotor - $elimRealisasi;

        // Belum ada realisasi di entitas mana pun = belum ada data (null), bukan 0% —
        // sama seperti F1 per entitas.
        $adaRealisasi = count(array_filter($baris, fn ($b) => $b['f1'] !== null)) > 0;
        $f1 = $targetBersih > 0 && $adaRealisasi ? round(min(100.0, max(0.0, $realisasiBersih / $targetBersih * 100)), 2) : null;

        // F2 grup: dibobot target revenue YTD; bila belum ada target sama sekali,
        // rata-rata sederhana entitas yang punya F2.
        $punyaF2 = array_filter($baris, fn ($b) => $b['f2'] !== null);
        $bobot = array_sum(array_map(fn ($b) => $b['revenue_target'], $punyaF2));
        $f2 = match (true) {
            $punyaF2 === [] => null,
            $bobot > 0 => round(array_sum(array_map(fn ($b) => $b['f2'] * $b['revenue_target'], $punyaF2)) / $bobot, 2),
            default => round(array_sum(array_column($punyaF2, 'f2')) / count($punyaF2), 2),
        };

        return [
            'entities' => $baris,
            'group' => [
                'revenue_target_gross' => $targetKotor,
                'revenue_actual_gross' => $realisasiKotor,
                'elimination_planned' => $elimRencana,
                'elimination_actual' => $elimRealisasi,
                'revenue_target_net' => $targetBersih,
                'revenue_actual_net' => $realisasiBersih,
                'f1' => $f1,
                'f2' => $f2,
                'f2_weighting' => $bobot > 0 ? 'revenue' : 'equal',
                'apex' => $f1 === null && $f2 === null ? null : Scorecard::apex(['revenue' => $f1, 'ratios' => $f2]),
                // Entitas yang sumbernya tidak terjangkau: angkanya kosong, bukan nol,
                // dan halaman memberi tahu bahwa grup belum lengkap.
                'unreachable' => array_values(array_map(
                    fn ($b) => $b['entity']->code,
                    array_filter($baris, fn ($b) => $b['status'] !== EntitySummary::STATUS_OK)
                )),
                'fetched_at' => collect($baris)->pluck('fetched_at')->filter()
                    ->map(fn ($w) => Carbon::parse($w))->min()?->toIso8601String(),
            ],
            'eliminations' => $eliminasi,
        ];
    }

    /**
     * Ringkasan satu entitas dari sumbernya. Sumber jarak jauh (database entitas
     * atau API) disimpan sebentar di cache supaya membuka halaman tidak memukul
     * server entitas berulang kali; sumber lokal selalu dibaca langsung.
     */
    private function summary(Entity $entitas, string $period): EntitySummary
    {
        $sumber = $this->sources->for($entitas);

        if ($sumber->name() === EntitySummary::SUMBER_LOKAL) {
            return $sumber->summary($entitas, $period);
        }

        $ttl = (int) config('bsc.consolidation_ttl', 300);
        $kunci = self::cacheKey($entitas, $period);

        if ($ttl <= 0) {
            return $sumber->summary($entitas, $period);
        }

        $tersimpan = Cache::get($kunci);

        if (is_array($tersimpan)) {
            return EntitySummary::fromArray($tersimpan);
        }

        $ringkasan = $sumber->summary($entitas, $period);

        // Kegagalan tidak di-cache lama: entitas yang sempat mati harus segera
        // tampil lagi begitu hidup.
        Cache::put($kunci, $ringkasan->toArray(), $ringkasan->ok() ? $ttl : min(30, $ttl));

        return $ringkasan;
    }
}
