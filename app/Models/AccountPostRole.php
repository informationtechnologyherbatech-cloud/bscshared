<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Model;

/**
 * Peta bagian 2: peran satu unit kerja pada satu pos akun.
 *
 * O (Pemilik) memegang lag measure atas pos akun itu dan dibebani target
 * rupiahnya; K (Kontributor) hanya boleh memasang lead/output measure.
 */
class AccountPostRole extends Model
{
    use BelongsToEntity;

    public const PEMILIK = 'O';

    public const KONTRIBUTOR = 'K';

    protected $fillable = ['entity_id', 'unit_code', 'post_code', 'role'];

    public static function label(?string $role): string
    {
        return match ($role) {
            self::PEMILIK => 'Pemilik',
            self::KONTRIBUTOR => 'Kontributor',
            default => 'Tidak terpetakan',
        };
    }
}
