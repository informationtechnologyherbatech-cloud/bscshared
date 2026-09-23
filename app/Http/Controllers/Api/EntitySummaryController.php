<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Period;
use App\Support\Bsc\EntitySummary;
use App\Support\Bsc\EntitySummaryBuilder;
use App\Support\EntityContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Satu-satunya pintu data yang dibuka aplikasi entitas untuk holding.
 *
 * Yang dikeluarkan hanya ringkasan entitas PEMASANGAN INI: skor F1/F2/apex,
 * revenue kumulatif, jumlah KPI & sasaran, 19 rasio, dan ringkasan per unit kerja.
 * Tidak ada pos akun, isi sasaran mutu, program kerja, maupun data entitas lain —
 * kode entitas pada permintaan diabaikan, jadi holding tidak bisa "menitip"
 * pertanyaan tentang entitas tetangga.
 */
class EntitySummaryController extends Controller
{
    public function __invoke(Request $request, EntityContext $context, EntitySummaryBuilder $builder): JsonResponse
    {
        $entitas = $this->entitasPemasangan($context);

        if (! $entitas) {
            return response()->json([
                'message' => 'Pemasangan ini tidak terikat satu entitas (BSC_DEFAULT_ENTITY belum diatur atau mode holding aktif).',
            ], 409);
        }

        $periode = (string) $request->query('period', '');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
            $periode = $context->runAs($entitas->id, fn () => Period::active());
        }

        $ringkasan = $context->runAs(
            $entitas->id,
            fn () => $builder->forEntity($entitas, $periode, EntitySummary::SUMBER_LOKAL)
        );

        return response()->json(['data' => $ringkasan->toArray()]);
    }

    /**
     * Entitas milik pemasangan ini. Instalasi holding sengaja tidak melayani API —
     * holding memanggil entitas, bukan sebaliknya.
     */
    private function entitasPemasangan(EntityContext $context): ?Entity
    {
        if ($context->isHoldingMode()) {
            return null;
        }

        $id = $context->installationEntityId();

        return $id ? Entity::whereKey($id)->first() : null;
    }
}
