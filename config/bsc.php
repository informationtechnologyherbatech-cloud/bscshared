<?php

return [

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

];
