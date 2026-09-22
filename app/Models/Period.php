<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use App\Support\EntityContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class Period extends Model
{
    use BelongsToEntity, HasFactory;

    protected $fillable = ['entity_id', 'period', 'status', 'apex_score'];

    public function isClosed(): bool
    {
        return $this->status === 'CLOSED';
    }

    /**
     * Periode aktif entitas aktif (YYYY-MM) — dipilih di navbar dan berlaku di semua
     * halaman. Alias lama; lihat active().
     */
    public static function currentPeriod(): string
    {
        return static::active();
    }

    /**
     * Periode aktif: pilihan pengguna di sesi (per entitas) bila periodenya masih ada;
     * selain itu bawaannya bulan berjalan, lalu periode terakhir sebelum bulan berjalan,
     * lalu periode terbaru, dan terakhir bulan berjalan meski belum dibuat.
     */
    public static function active(): string
    {
        $daftar = static::list();
        $pilihan = Session::get(static::sessionKey());

        if (is_string($pilihan) && in_array($pilihan, $daftar, true)) {
            return $pilihan;
        }

        return static::defaultFrom($daftar);
    }

    /**
     * Periode berstatus CLOSED dalam satu tahun — datanya tidak boleh diubah.
     *
     * @return array<int, string>
     */
    public static function closedIn(string $year): array
    {
        return static::where('period', 'like', $year.'-%')->where('status', 'CLOSED')->pluck('period')->all();
    }

    /** Tahun periode aktif (YYYY). */
    public static function activeYear(): string
    {
        return substr(static::active(), 0, 4);
    }

    /** Simpan periode aktif; diabaikan bila periode itu belum dibuat. */
    public static function setActive(string $period): bool
    {
        if (! in_array($period, static::list(), true)) {
            return false;
        }

        Session::put(static::sessionKey(), $period);

        return true;
    }

    /** @param  array<int, string>  $daftar  terbaru lebih dulu */
    public static function defaultFrom(array $daftar): string
    {
        $bulanIni = now()->format('Y-m');

        if (in_array($bulanIni, $daftar, true)) {
            return $bulanIni;
        }

        foreach ($daftar as $p) {
            if ($p < $bulanIni) {
                return $p;
            }
        }

        return $daftar[0] ?? $bulanIni;
    }

    private static function sessionKey(): string
    {
        return 'bsc.periode_aktif.'.(app(EntityContext::class)->id() ?? 'semua');
    }

    /**
     * Daftar periode entitas aktif, terbaru lebih dulu.
     *
     * @return array<int, string>
     */
    public static function list(): array
    {
        return static::orderByDesc('period')->pluck('period')->all();
    }
}
