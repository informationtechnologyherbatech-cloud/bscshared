<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Satu sumber kebenaran untuk syarat kata sandi.
 *
 * Dipakai aturan validasi maupun teks bantuan di layar, sehingga yang
 * ditampilkan kepada pengguna selalu sama dengan yang benar-benar diperiksa.
 * Setelan angkanya ada di config/security.php.
 */
class PasswordPolicy
{
    /** Aturan validasi Laravel untuk kata sandi baru. */
    public static function rule(): Password
    {
        $rule = Password::min(self::minLength());

        if (config('security.password.mixed_case', true)) {
            $rule->mixedCase();
        }

        if (config('security.password.numbers', true)) {
            $rule->numbers();
        }

        if (config('security.password.symbols', true)) {
            $rule->symbols();
        }

        if (config('security.password.uncompromised', false)) {
            $rule->uncompromised();
        }

        return $rule;
    }

    public static function minLength(): int
    {
        return max(8, (int) config('security.password.min_length', 10));
    }

    /**
     * Daftar syarat untuk ditampilkan di layar.
     *
     * @return array<int, array{label: string, regex: string}>
     *                                                         Regex ditulis dalam sintaks JavaScript agar dapat dipakai
     *                                                         langsung oleh daftar periksa langsung di formulir.
     */
    public static function checklist(): array
    {
        $items = [[
            'label' => 'Minimal '.self::minLength().' karakter',
            'regex' => '.{'.self::minLength().',}',
        ]];

        if (config('security.password.mixed_case', true)) {
            $items[] = ['label' => 'Ada huruf besar (A-Z)', 'regex' => '[A-Z]'];
            $items[] = ['label' => 'Ada huruf kecil (a-z)', 'regex' => '[a-z]'];
        }

        if (config('security.password.numbers', true)) {
            $items[] = ['label' => 'Ada angka (0-9)', 'regex' => '[0-9]'];
        }

        if (config('security.password.symbols', true)) {
            $items[] = ['label' => 'Ada karakter khusus (!@#$%...)', 'regex' => '[^A-Za-z0-9]'];
        }

        return $items;
    }

    /** Ringkasan syarat dalam satu kalimat. */
    public static function hint(): string
    {
        return 'Minimal '.self::minLength().' karakter, mengandung huruf besar, huruf kecil, angka, dan karakter khusus.';
    }
}
