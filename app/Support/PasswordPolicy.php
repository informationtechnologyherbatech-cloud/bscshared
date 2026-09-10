<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Satu sumber kebenaran untuk syarat kata sandi.
 *
 * Aturan validasi, daftar periksa di layar, dan kalimat bantuan seluruhnya
 * dibangun dari daftar syarat yang sama, sehingga yang dijanjikan kepada
 * pengguna tidak pernah berbeda dari yang benar-benar diperiksa — termasuk
 * ketika sebagian syarat dimatikan lewat config/security.php.
 */
class PasswordPolicy
{
    /** Aturan validasi Laravel untuk kata sandi baru. */
    public static function rule(): Password
    {
        $rule = Password::min(self::minLength());

        if (self::requires('mixed_case')) {
            $rule->mixedCase();
        }

        if (self::requires('numbers')) {
            $rule->numbers();
        }

        if (self::requires('symbols')) {
            $rule->symbols();
        }

        if (self::requires('uncompromised', false)) {
            $rule->uncompromised();
        }

        return $rule;
    }

    public static function minLength(): int
    {
        return max(8, (int) config('security.password.min_length', 10));
    }

    /**
     * Daftar syarat komposisi yang aktif.
     *
     * @return array<int, array{label: string, ringkas: string, regex: string}>
     *                                                                          Regex ditulis dalam sintaks JavaScript agar dapat dipakai langsung
     *                                                                          oleh daftar periksa langsung di formulir.
     */
    private static function requirements(): array
    {
        $items = [[
            'label' => 'Minimal '.self::minLength().' karakter',
            'ringkas' => 'minimal '.self::minLength().' karakter',
            'regex' => '.{'.self::minLength().',}',
        ]];

        if (self::requires('mixed_case')) {
            $items[] = [
                'label' => 'Ada huruf besar (A-Z)',
                'ringkas' => 'huruf besar',
                'regex' => '[A-Z]',
            ];
            $items[] = [
                'label' => 'Ada huruf kecil (a-z)',
                'ringkas' => 'huruf kecil',
                'regex' => '[a-z]',
            ];
        }

        if (self::requires('numbers')) {
            $items[] = [
                'label' => 'Ada angka (0-9)',
                'ringkas' => 'angka',
                'regex' => '[0-9]',
            ];
        }

        if (self::requires('symbols')) {
            $items[] = [
                'label' => 'Ada karakter khusus (!@#$%...)',
                'ringkas' => 'karakter khusus',
                'regex' => '[^A-Za-z0-9]',
            ];
        }

        return $items;
    }

    /**
     * Daftar syarat untuk ditampilkan sebagai daftar periksa di formulir.
     *
     * @return array<int, array{label: string, regex: string}>
     */
    public static function checklist(): array
    {
        return array_map(
            fn (array $item) => ['label' => $item['label'], 'regex' => $item['regex']],
            self::requirements()
        );
    }

    /**
     * Ringkasan syarat dalam satu kalimat, mengikuti syarat yang benar-benar
     * aktif — bukan daftar tetap.
     */
    public static function hint(): string
    {
        $items = self::requirements();
        $panjang = array_shift($items);

        $kalimat = ucfirst($panjang['ringkas']);

        if ($items !== []) {
            $kalimat .= ', mengandung '.self::gabung(array_column($items, 'ringkas'));
        }

        if (self::requires('uncompromised', false)) {
            $kalimat .= ', dan belum pernah muncul pada kebocoran data publik';
        }

        return $kalimat.'.';
    }

    /** Apakah satu syarat sedang diaktifkan. */
    private static function requires(string $key, bool $default = true): bool
    {
        return (bool) config('security.password.'.$key, $default);
    }

    /**
     * Gabungkan daftar menjadi kalimat Indonesia: "a, b, dan c".
     *
     * @param  array<int, string>  $bagian
     */
    private static function gabung(array $bagian): string
    {
        if (count($bagian) === 1) {
            return $bagian[0];
        }

        $terakhir = array_pop($bagian);

        return implode(', ', $bagian).', dan '.$terakhir;
    }
}
