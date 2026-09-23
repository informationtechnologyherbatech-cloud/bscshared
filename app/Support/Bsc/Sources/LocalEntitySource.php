<?php

namespace App\Support\Bsc\Sources;

use App\Models\Entity;
use App\Support\Bsc\EntitySummary;
use App\Support\Bsc\EntitySummaryBuilder;
use App\Support\EntityContext;

/**
 * Entitas yang datanya ada di database ini juga (pemasangan tunggal atau
 * pengembangan). Isolasinya bersandar pada global scope BelongsToEntity.
 */
class LocalEntitySource implements EntitySource
{
    public function __construct(
        private EntityContext $context,
        private EntitySummaryBuilder $builder,
    ) {}

    public function summary(Entity $entitas, string $period): EntitySummary
    {
        return $this->context->runAs(
            $entitas->id,
            fn () => $this->builder->forEntity($entitas, $period, EntitySummary::SUMBER_LOKAL)
        );
    }

    public function name(): string
    {
        return EntitySummary::SUMBER_LOKAL;
    }
}
