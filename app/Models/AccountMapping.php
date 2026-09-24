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

    /**
     * Jenis akun Odoo (`account.account.account_type`) → pos akun BSC.
     *
     * Dipakai untuk MENGUSULKAN pemetaan pada bagan akun yang berisi ratusan
     * baris; usulannya tetap dapat diubah atau dihapus. Hanya jenis yang
     * artinya tidak mendua yang dicantumkan.
     *
     * Sengaja TIDAK diusulkan:
     *   - PA05 Persediaan — di Odoo berjenis asset_current, tidak terbedakan
     *     dari uang muka dan aset lancar lain kecuali dari namanya;
     *   - PA09 Aset lancar, PA11 Total aset, PA12 Total liabilitas — ketiganya
     *     JUMLAH yang memuat akun yang sama dengan pos lain, sedangkan satu kode
     *     akun hanya boleh menunjuk satu pos;
     *   - PA04 beban tenaga kerja, PA14 modal disetor, PA15–PA16 data HRIS —
     *     tidak dapat dikenali dari jenis akunnya.
     * Semuanya diisi di menu Pos Akun.
     */
    public const DARI_JENIS_ODOO = [
        'income' => 'PA01',
        'income_other' => 'PA01',
        'expense_direct_cost' => 'PA02',
        'expense' => 'PA03',
        'expense_depreciation' => 'PA03',
        'asset_receivable' => 'PA06',
        'liability_payable' => 'PA07',
        'asset_cash' => 'PA08',
        'liability_current' => 'PA10',
        'equity' => 'PA13',
        'equity_unaffected' => 'PA13',
    ];

    public static function usulanDariJenis(?string $jenisOdoo): ?string
    {
        return self::DARI_JENIS_ODOO[$jenisOdoo] ?? null;
    }

    public function postName(): string
    {
        return AccountPosts::all()[$this->post_code]['name'] ?? $this->post_code;
    }
}
