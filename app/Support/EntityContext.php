<?php

namespace App\Support;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Entitas yang sedang aktif untuk permintaan ini.
 *
 * Diselesaikan secara malas dari pengguna yang login, bukan dari middleware,
 * supaya berlaku pula pada permintaan pembaruan Livewire — yang tidak
 * melewati middleware rute — dan tidak berlaku di konsol atau antrean yang
 * memang tidak punya pengguna.
 *
 * Aturannya:
 *   - instalasi satu entitas (BSC_HOLDING_MODE=false): semua pengguna berada
 *     di entitas instalasi (BSC_DEFAULT_ENTITY), tanpa pengalih;
 *   - instalasi holding — pengguna yang terikat satu entitas selalu berada di
 *     entitas itu;
 *   - pengguna tanpa entitas (level holding) memilih entitas lewat pengalih,
 *     disimpan di sesi; bila belum memilih, dipakai entitas bawaan instalasi
 *     (BSC_DEFAULT_ENTITY) — lihat defaultId().
 */
class EntityContext
{
    public const SESSION_KEY = 'active_entity_id';

    /** false = belum diganti manual; null/int = diganti (dipakai tes & konsol). */
    private int|null|false $override = false;

    /** @var array<int, Collection<int, Entity>> */
    private array $accessible = [];

    private ?int $default = null;

    private bool $defaultResolved = false;

    /** false = belum dicari; null = kode di .env tidak dikenal/nonaktif. */
    private int|null|false $installation = false;

    /** Entitas yang sudah dimuat dalam request ini (navbar, layout & komponen memanggil entity() berulang). */
    private array $loaded = [];

    public function id(): ?int
    {
        if ($this->override !== false) {
            return $this->override;
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if (! $this->isHoldingMode() && ($instalasi = $this->installationEntityId())) {
            return $instalasi;
        }

        if ($user->entity_id) {
            return (int) $user->entity_id;
        }

        $dariSesi = (int) session(self::SESSION_KEY);

        if ($dariSesi && $this->accessibleFor($user)->contains('id', $dariSesi)) {
            return $dariSesi;
        }

        return $this->defaultId();
    }

    public function entity(): ?Entity
    {
        $id = $this->id();

        return $id ? ($this->loaded[$id] ??= Entity::find($id)) : null;
    }

    /**
     * Entitas yang boleh dibuka pengguna.
     *
     * @return Collection<int, Entity>
     */
    public function accessibleFor(User $user): Collection
    {
        if (! $this->isHoldingMode() && ($instalasi = $this->installationEntityId())) {
            return $this->accessible[$user->id] ??= Entity::whereKey($instalasi)->get();
        }

        return $this->accessible[$user->id] ??= $user->entity_id
            ? Entity::whereKey($user->entity_id)->get()
            : Entity::active()->get();
    }

    /** Pengguna level holding dapat berpindah antarentitas — hanya di instalasi holding. */
    public function canSwitch(User $user): bool
    {
        return $this->isHoldingUser($user) && $this->accessibleFor($user)->count() > 1;
    }

    /**
     * Pengguna tingkat holding: pada pemasangan holding dan tidak terikat satu
     * entitas. Berbeda dengan canSwitch(), ini tidak menuntut adanya lebih dari
     * satu entitas — holding yang untuk sementara hanya punya satu entitas aktif
     * tetap boleh membuka halaman Konsolidasi dan Sumber Data Entitas.
     */
    public function isHoldingUser(User $user): bool
    {
        return $this->isHoldingMode() && ! $user->entity_id;
    }

    /** Instalasi holding (BSC_HOLDING_MODE=true) atau instalasi satu entitas. */
    public function isHoldingMode(): bool
    {
        return (bool) config('bsc.holding_mode');
    }

    /** Entitas instalasi (BSC_DEFAULT_ENTITY), bila kodenya dikenal & aktif. */
    public function installationEntityId(): ?int
    {
        if ($this->installation === false) {
            $this->installation = Entity::configuredDefault()?->id;
        }

        return $this->installation;
    }

    public function switchTo(User $user, int $entityId): bool
    {
        if (! $this->canSwitch($user) || ! $this->accessibleFor($user)->contains('id', $entityId)) {
            return false;
        }

        session([self::SESSION_KEY => $entityId]);

        return true;
    }

    /** Paksa entitas tertentu (konsol, tes, atau proses latar). */
    public function use(?int $entityId): void
    {
        $this->override = $entityId;
    }

    /**
     * Jalankan $fn dengan entitas tertentu aktif, lalu kembalikan konteks
     * semula — juga bila $fn melempar galat. Dipakai konsolidasi holding untuk
     * menghitung skor tiap entitas dengan kueri berentitas yang sama.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public function runAs(int $entityId, callable $fn): mixed
    {
        $sebelumnya = $this->override;
        $this->override = $entityId;

        try {
            return $fn();
        } finally {
            $this->override = $sebelumnya;
        }
    }

    /**
     * Buang ingatan sementara (entitas termuat, daftar akses, entitas instalasi)
     * TANPA membatalkan pilihan entitas yang sedang berlaku — dipakai saat membaca
     * database entitas lain, karena id entitas berbeda di tiap database.
     */
    public function flushCache(): void
    {
        $this->accessible = [];
        $this->loaded = [];
        $this->default = null;
        $this->defaultResolved = false;
        $this->installation = false;
    }

    public function forget(): void
    {
        $this->override = false;
        $this->flushCache();
    }

    /**
     * Entitas bawaan bagi pengguna holding yang belum memilih:
     *   1. entitas yang ditetapkan instalasi (BSC_DEFAULT_ENTITY);
     *   2. bila kodenya tidak dikenal/nonaktif: entitas yang datanya paling baru
     *      diperbarui, supaya pengguna mendarat di tempat datanya berada;
     *   3. entitas aktif pertama.
     */
    private function defaultId(): ?int
    {
        if ($this->defaultResolved) {
            return $this->default;
        }

        $this->defaultResolved = true;

        if ($terpilih = Entity::configuredDefault()) {
            return $this->default = $terpilih->id;
        }

        // Kueri langsung ke tabel: model Period memakai pembatas entitas, yang
        // memanggil kelas ini — lewat model akan berputar tanpa akhir.
        $terbaru = DB::table('periods')
            ->join('entities', 'entities.id', '=', 'periods.entity_id')
            ->where('entities.is_active', true)
            ->orderByDesc('periods.updated_at')
            ->value('periods.entity_id');

        return $this->default = $terbaru
            ? (int) $terbaru
            : Entity::active()->value('id');
    }
}
