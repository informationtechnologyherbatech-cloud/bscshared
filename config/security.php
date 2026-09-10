<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Header Keamanan Respons
    |--------------------------------------------------------------------------
    |
    | Dipasang oleh App\Http\Middleware\SecurityHeaders pada setiap respons web.
    | Setel nilai menjadi null untuk tidak mengirim header terkait.
    |
    */

    'headers' => [
        // Cegah peramban menebak-nebak tipe berkas (dasar serangan lewat unggahan).
        'X-Content-Type-Options' => 'nosniff',

        // Cegah aplikasi disematkan di situs lain (clickjacking).
        'X-Frame-Options' => 'DENY',

        // Jangan bocorkan URL internal ke situs pihak ketiga.
        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        // Matikan perangkat yang memang tidak dipakai aplikasi ini.
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',

        // Aplikasi internal: jangan diindeks mesin pencari.
        'X-Robots-Tag' => 'noindex, nofollow',

        // Penyaring XSS bawaan peramban lama justru menimbulkan celah;
        // rekomendasi terkini adalah mematikannya dan mengandalkan CSP.
        'X-XSS-Protection' => '0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Syarat Kata Sandi
    |--------------------------------------------------------------------------
    |
    | Berlaku saat membuat pengguna baru atau mengganti kata sandi pengguna
    | yang sudah ada (lihat App\Support\PasswordPolicy). Kata sandi lama tidak
    | dipaksa berubah, tetapi setiap kali diubah harus memenuhi syarat ini.
    |
    | 'uncompromised' memeriksa kata sandi ke basis data kebocoran publik
    | (haveibeenpwned) lewat jaringan; matikan bila server tidak punya akses
    | internet.
    |
    */

    'password' => [
        'min_length' => (int) env('PASSWORD_MIN_LENGTH', 10),
        'mixed_case' => true,
        'numbers' => true,
        'symbols' => true,
        'uncompromised' => (bool) env('PASSWORD_CHECK_LEAKED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Hanya dikirim pada koneksi HTTPS, sehingga tidak mengganggu pengembangan
    | lokal lewat http://localhost.
    |
    */

    'hsts' => [
        'enabled' => true,
        'max_age' => 31536000,
        'include_subdomains' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Pertahanan berlapis terhadap XSS: membatasi dari mana skrip, gaya, gambar
    | dan font boleh dimuat. Daftar sumber di bawah mencerminkan aset yang
    | benar-benar dipakai aplikasi (AdminLTE/Bootstrap/Font Awesome offline
    | dengan cadangan CDN, Google Fonts, dan reCAPTCHA).
    |
    | 'unsafe-inline' masih diperlukan karena AdminLTE dan tampilan aplikasi
    | memakai gaya serta skrip sebaris; 'unsafe-eval' diperlukan Alpine.js
    | yang dibundel Livewire.
    |
    | Setel 'enabled' => false bila ada aset yang terblokir, atau
    | 'report_only' => true untuk memantau pelanggaran tanpa memblokir.
    |
    */

    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'report_only' => env('CSP_REPORT_ONLY', false),

        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => [
                "'self'",
                "'unsafe-inline'",
                "'unsafe-eval'",
                'https://cdnjs.cloudflare.com',
                'https://cdn.jsdelivr.net',
                'https://www.google.com',
                'https://www.gstatic.com',
            ],
            'style-src' => [
                "'self'",
                "'unsafe-inline'",
                'https://fonts.googleapis.com',
                'https://cdnjs.cloudflare.com',
                'https://cdn.jsdelivr.net',
            ],
            'font-src' => [
                "'self'",
                'data:',
                'https://fonts.gstatic.com',
                'https://cdnjs.cloudflare.com',
            ],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'"],
            'frame-src' => ['https://www.google.com'],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ],
    ],

];
