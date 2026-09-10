<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bobot Agregasi Apex Score
    |--------------------------------------------------------------------------
    |
    | Apex Score (Level 1 piramida) adalah rata-rata terbobot dari tiga tingkat
    | di bawahnya, seluruhnya dihitung dari data periode berjalan:
    |
    |   - ratios      : rata-rata capaian rasio keuangan (Level 2)
    |   - objectives  : rata-rata capaian sasaran mutu departemen (Level 3)
    |   - action_plans: rata-rata progres program kerja (Level 4)
    |
    | Bobot boleh disesuaikan kebijakan manajemen. Bila salah satu tingkat
    | belum punya data pada periode terpilih, bobotnya dinormalisasi ulang ke
    | tingkat yang tersedia sehingga skor tidak jatuh secara semu.
    |
    */

    'apex_weights' => [
        'ratios' => 0.45,
        'objectives' => 0.35,
        'action_plans' => 0.20,
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
