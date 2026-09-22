<?php

namespace App\Livewire\Concerns;

use App\Models\Period;

/**
 * Halaman bulanan mengikuti periode aktif di navbar, dan sebaliknya: memilih periode
 * di halaman ikut mengubah periode aktif sehingga keduanya tidak pernah berbeda.
 */
trait FollowsActivePeriod
{
    /**
     * Periode awal halaman: periode dari URL (tautan antarhalaman) bila ada dan
     * valid — sekaligus dijadikan periode aktif — selain itu periode aktif.
     */
    protected function initialPeriod(?string $dariUrl): string
    {
        if (is_string($dariUrl) && $dariUrl !== '' && Period::setActive($dariUrl)) {
            return $dariUrl;
        }

        return Period::active();
    }

    /** Jadikan periode pilihan halaman sebagai periode aktif dan perbarui navbar. */
    protected function shareActivePeriod(string $period): void
    {
        if (Period::setActive($period)) {
            $this->dispatch('periode-aktif', period: $period);
        }
    }
}
