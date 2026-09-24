<?php

namespace App\Support\Bsc\Integration;

/**
 * Hasil satu kali pemasukan data dari luar. Bentuknya sama untuk unggahan
 * berkas maupun tarikan dari Odoo, sehingga layar, perintah konsol, dan jejak
 * audit tidak perlu tahu data itu datang dari mana.
 */
class IntakeResult
{
    public const DITERIMA = 'diterima';

    public const GANDA = 'ganda';

    public const DITOLAK = 'ditolak';

    /**
     * @param  array<string, float>  $posts  pos akun yang terisi: PA01 => nilai
     * @param  array<int, string>  $unmapped  kode akun sumber yang belum dipetakan
     * @param  array<int, string>  $problems  baris yang tidak dapat dibaca
     */
    public function __construct(
        public readonly string $outcome,
        public readonly string $period,
        public readonly string $message,
        public readonly array $posts = [],
        public readonly array $unmapped = [],
        public readonly array $problems = [],
        public readonly ?float $revenue = null,
        public readonly int $ratios = 0,
    ) {}

    public function ok(): bool
    {
        return $this->outcome === self::DITERIMA;
    }
}
