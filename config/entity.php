<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas Entitas Pengguna Aplikasi
    |--------------------------------------------------------------------------
    |
    | Super Apps BSC bersifat multi-entitas: satu basis kode dapat dipakai oleh
    | perusahaan mana pun. Seluruh identitas (nama entitas, nama perusahaan,
    | alamat, kontak, logo, favicon) disimpan di tabel `app_settings` dan
    | dikelola lewat menu Pengaturan.
    |
    | Nilai di bawah ini hanyalah CADANGAN (fallback) yang dipakai ketika
    | pengaturan belum diisi — bukan sumber kebenaran.
    |
    */

    'defaults' => [
        'app_name' => 'Super Apps BSC',
        'app_tagline' => 'Balanced Scorecard Enterprise',
        'app_year' => '2026',
        'app_primary_color' => '#17a2b8',

        'entity_name' => 'Herbatech Innopharma',
        'company_name' => 'PT Herbatech Innopharma Industry',
        'company_address' => '',
        'company_phone' => '',
        'company_email' => '',
        'company_website' => '',
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
