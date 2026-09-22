<?php

namespace App\Support\Bsc;

use App\Models\AccountPostRole;

/**
 * Sheet "Peta Rasio-Akun-Dept" untuk entitas aktif.
 *
 *   Bagian 1 (tetap)  : pos akun pembentuk tiap rasio — RatioLibrary::posts().
 *   Bagian 2 (isian)  : peran unit pada pos akun — tabel account_post_roles.
 *   Bagian 3 (otomatis): rasio yang boleh diklaim unit = rasio yang salah satu
 *                        pos pembentuknya dimiliki/dikontribusi unit itu.
 */
class PostMap
{
    /** PA01 boleh punya beberapa Pemilik: tiap unit channel memiliki porsinya (dipecah di L1). */
    public const MULTI_OWNER_POSTS = ['PA01'];

    /** @var array<string, array<string, string>> unit => [pos => O|K] */
    private array $roles;

    /** @param  array<string, array<string, string>>|null  $roles */
    public function __construct(?array $roles = null)
    {
        $this->roles = $roles ?? self::loadRoles();
    }

    /** @return array<string, array<string, string>> */
    public static function loadRoles(): array
    {
        $hasil = [];

        foreach (AccountPostRole::all(['unit_code', 'post_code', 'role']) as $r) {
            $hasil[$r->unit_code][$r->post_code] = $r->role;
        }

        return $hasil;
    }

    /** @return array<string, array<string, string>> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function roleOf(string $unit, string $post): ?string
    {
        return $this->roles[$unit][$post] ?? null;
    }

    /**
     * Rasio yang boleh diklaim unit (bagian 3), urut sesuai pustaka.
     *
     * @return array<int, string>
     */
    public function claimableRatios(string $unit): array
    {
        $pos = array_keys($this->roles[$unit] ?? []);

        return array_values(array_filter(
            array_keys(RatioLibrary::posts()),
            fn ($rasio) => array_intersect(RatioLibrary::postsOf($rasio), $pos) !== []
        ));
    }

    /** Boleh mengklaim dampak ke kode ini (rasio atau REV)? */
    public function canClaim(string $unit, string $impact): bool
    {
        return array_intersect(RatioLibrary::postsOf($impact), array_keys($this->roles[$unit] ?? [])) !== [];
    }

    /**
     * Baris "Σ Pemilik per pos akun" & "Cek (harus tepat 1 pemilik)".
     *
     * @return array<string, array{owners: array<int, string>, ok: bool, note: string}>
     */
    public function ownerChecks(): array
    {
        $hasil = [];

        foreach (array_keys(AccountPosts::all()) as $pos) {
            $pemilik = [];
            foreach ($this->roles as $unit => $peran) {
                if (($peran[$pos] ?? null) === AccountPostRole::PEMILIK) {
                    $pemilik[] = $unit;
                }
            }

            $jamak = in_array($pos, self::MULTI_OWNER_POSTS, true);
            $hasil[$pos] = [
                'owners' => $pemilik,
                'ok' => count($pemilik) === 1 || ($jamak && count($pemilik) > 1),
                'note' => match (true) {
                    $pemilik === [] => 'Perlu ditetapkan',
                    count($pemilik) === 1 || $jamak => 'OK',
                    default => 'Ganda — satu pos akun hanya boleh satu Pemilik',
                },
            ];
        }

        return $hasil;
    }
}
