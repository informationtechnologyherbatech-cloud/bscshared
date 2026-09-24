<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use App\Support\Bsc\AccountPosts;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu kode akun sistem sumber → satu pos akun BSC.
 *
 * Milik entitas: bagan akun Herbatech tidak berlaku untuk Erdigma, dan
 * pemetaan satu entitas tidak boleh terlihat oleh entitas lain.
 */
class AccountMapping extends Model
{
    use BelongsToEntity;

    protected $fillable = ['entity_id', 'source_code', 'source_name', 'post_code', 'invert', 'updated_by'];

    protected function casts(): array
    {
        return ['invert' => 'boolean'];
    }

    /**
     * Pos akun yang lazimnya bersaldo kredit di buku besar. Dipakai sebagai
     * nilai awal kotak "balik tanda" saat pemetaan baru dibuat — tetap dapat
     * diubah, karena tiap bagan akun punya kebiasaannya sendiri.
     */
    public const LAZIM_KREDIT = ['PA01', 'PA07', 'PA10', 'PA12', 'PA13', 'PA14'];

    public static function defaultInvert(string $postCode): bool
    {
        return in_array($postCode, self::LAZIM_KREDIT, true);
    }

    public function postName(): string
    {
        return AccountPosts::all()[$this->post_code]['name'] ?? $this->post_code;
    }
}
