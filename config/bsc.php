<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Entitas Bawaan
    |--------------------------------------------------------------------------
    |
    | Kode entitas (HERBAEMAS, HERBATECH, AEJ, ERDIGMA) yang:
    |   - dibuka pertama kali oleh pengguna level holding yang belum memilih
    |     entitas lewat pengalih di navbar, dan
    |   - menjadi pemilik data contoh saat `php artisan db:seed`.
    |
    | Pengguna yang ditautkan ke satu entitas selalu melihat entitasnya sendiri,
    | apa pun nilai ini. Setiap instalasi dapat memakai nilai berbeda lewat .env.
    |
    */

    'default_entity' => strtoupper((string) env('BSC_DEFAULT_ENTITY', 'ERDIGMA')),

    /*
    |--------------------------------------------------------------------------
    | Mode Holding
    |--------------------------------------------------------------------------
    |
    | false (bawaan) — instalasi satu entitas: semua pengguna terkunci di
    |   default_entity; tidak ada pengalih entitas dan menu Konsolidasi Holding.
    | true — instalasi holding: pengguna level holding (tanpa entitas) dapat
    |   berpindah antarentitas dan membuka Konsolidasi Holding; default_entity
    |   menjadi entitas yang dibuka pertama.
    |
    */

    'holding_mode' => (bool) env('BSC_HOLDING_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Bobot Skor Puncak (Apex)
    |--------------------------------------------------------------------------
    |
    | Mengikuti metodologi pada Cascading_Revenue_Rasio_KPI_Erdigma_2026.xlsx
    | (sheet Asumsi, bagian A): "skor puncak = 0,45 × F1 + 0,55 × F2".
    |
    |   - revenue : F1 — pencapaian revenue KUMULATIF Jan s.d. bulan berjalan
    |               (menu Target Revenue, Tingkat 1)
    |   - ratios  : F2 — skor rasio keuangan (Tingkat 2)
    |
    | Tingkat 3 (sasaran mutu) dan Tingkat 4 (program kerja) sengaja TIDAK
    | masuk rumus ini: menurut metodologinya, keduanya menggerakkan rasio lewat
    | pos akun, sehingga pengaruhnya sudah tercermin di F2 — memasukkannya lagi
    | berarti menghitung dua kali.
    |
    | Bila salah satu belum punya data pada periode terpilih, bobotnya
    | dinormalisasi ke yang tersedia, sehingga skor tidak jatuh secara semu.
    |
    */

    'apex_weights' => [
        'revenue' => 0.45,
        'ratios' => 0.55,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ambang Kesegaran Data
    |--------------------------------------------------------------------------
    |
    | Jumlah jam sejak sinkronisasi terakhir sebelum dashboard menampilkan
    | peringatan "data belum disinkronkan".
    |
    */

    'stale_after_hours' => 26,

    /*
    |--------------------------------------------------------------------------
    | Bobot Kelompok Rasio (Tingkat 2)
    |--------------------------------------------------------------------------
    |
    | Sheet "Asumsi" bagian B — sudah disepakati di BSC. Jumlah bobot rasio
    | aktif tiap kelompok pada Katalog Rasio sebaiknya sama dengan angka ini;
    | halaman Katalog Rasio menampilkan pemeriksaannya.
    |
    */

    'ratio_groups' => [
        'Profitabilitas' => 30,
        'Aktivitas' => 25,
        'Produktivitas' => 20,
        'Likuiditas' => 15,
        'Solvabilitas' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rubrik Skor
    |--------------------------------------------------------------------------
    |
    | Sheet "Asumsi" bagian C, dari kolom Metode Pengukuran format Sasaran Mutu:
    | pencapaian minimal (%) => skor. Dibaca dari atas; yang pertama terpenuhi
    | dipakai.
    |
    */

    'rubric' => [
        90 => 100,
        80 => 80,
        75 => 70,
        65 => 60,
        0 => 50,
    ],

];
