<?php

/*
|--------------------------------------------------------------------------
| Profil Entitas Grup
|--------------------------------------------------------------------------
|
| Satu sumber untuk nama & nama resmi keempat entitas di bawah holding
| Erhanesia Mulia Corpora. Dipakai EntityStructureSeeder (tabel entities) dan
| sebagai identitas bawaan instalasi di bawah.
|
*/
$profil = [
    'HERBAEMAS' => ['name' => 'Herbaemas', 'legal_name' => 'PT Herba Emas Wahidatama', 'industry' => 'manufaktur'],
    'HERBATECH' => ['name' => 'Herbatech', 'legal_name' => 'PT Herbatech Innopharma Industry', 'industry' => 'manufaktur'],
    'AEJ' => ['name' => 'AEJ', 'legal_name' => 'PT Abithama Emas Juara', 'industry' => 'manufaktur'],
    'ERDIGMA' => ['name' => 'Erdigma', 'legal_name' => 'PT Erhanesia Digima Mukitama', 'industry' => 'digital_marketing'],
];

$holding = ['name' => 'Erhanesia Mulia Corpora', 'legal_name' => 'Erhanesia Mulia Corpora'];

// Identitas bawaan mengikuti jenis instalasi (.env): instalasi holding memakai
// identitas holding; instalasi satu entitas memakai profil entitas itu.
$kode = strtoupper((string) env('BSC_DEFAULT_ENTITY', 'ERDIGMA'));
$instalasi = filter_var(env('BSC_HOLDING_MODE', false), FILTER_VALIDATE_BOOL)
    ? $holding
    : ($profil[$kode] ?? $profil['ERDIGMA']);

return [

    'profiles' => $profil,

    'holding' => $holding,

    /*
    |--------------------------------------------------------------------------
    | Identitas Entitas Pengguna Aplikasi
    |--------------------------------------------------------------------------
    |
    | Seluruh identitas (nama entitas, nama perusahaan, alamat, kontak, logo,
    | favicon) disimpan di tabel `app_settings` dan dikelola lewat menu
    | Setting Sistem (tab Identitas Aplikasi & tab Entitas).
    |
    | Nilai di bawah ini adalah nilai awal saat migrasi dan nilai "Reset
    | Default". Nama entitas & perusahaan mengikuti BSC_DEFAULT_ENTITY /
    | BSC_HOLDING_MODE di .env.
    |
    */

    'defaults' => [
        'app_name' => 'Super Apps BSC',
        'app_tagline' => 'Balanced Scorecard Enterprise',
        'app_year' => '2026',
        'app_primary_color' => '#17a2b8',

        'entity_name' => $instalasi['name'],
        'company_name' => $instalasi['legal_name'],
        'company_address' => '',
        'company_phone' => '',
        'company_email' => '',
        'company_website' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Nilai Bawaan Lama
    |--------------------------------------------------------------------------
    |
    | Identitas yang dulu ditulis mati sebagai bawaan (versi satu entitas
    | Herbatech). Migrasi penyelaras hanya mengganti identitas yang masih sama
    | dengan nilai ini — isian yang sudah diubah pengguna tidak disentuh.
    |
    */

    'legacy_defaults' => [
        'entity_name' => 'Herbatech Innopharma',
        'company_name' => 'PT Herbatech Innopharma Industry',
    ],

    /*
    |--------------------------------------------------------------------------
    | Daftar Kunci Pengaturan
    |--------------------------------------------------------------------------
    |
    | Dipakai seeder & halaman Pengaturan agar kunci pengaturan hanya
    | didefinisikan pada satu tempat.
    |
    */

    'app_keys' => ['app_name', 'app_tagline', 'app_year', 'app_primary_color'],

    'entity_keys' => ['entity_name', 'company_name', 'company_address', 'company_phone', 'company_email', 'company_website'],

    'keys' => [
        'app_name',
        'app_tagline',
        'app_year',
        'app_primary_color',
        'entity_name',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_website',
        'app_logo',
        'app_favicon',
    ],

];
