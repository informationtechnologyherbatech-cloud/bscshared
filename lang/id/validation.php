<?php

/*
|--------------------------------------------------------------------------
| Pesan Validasi Bahasa Indonesia
|--------------------------------------------------------------------------
|
| Hanya aturan yang benar-benar dipakai aplikasi ini yang diterjemahkan.
| Kunci yang tidak ada di sini otomatis jatuh ke APP_FALLBACK_LOCALE (en).
|
*/

return [

    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'email' => 'Format :attribute tidak valid.',
    'url' => 'Format :attribute harus berupa alamat web yang valid.',
    'digits' => ':attribute harus terdiri dari :digits angka.',
    'regex' => 'Format :attribute tidak sesuai.',
    'unique' => ':attribute sudah digunakan.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'image' => ':attribute harus berupa berkas gambar.',
    'mimes' => ':attribute harus berjenis: :values.',
    'mimetypes' => ':attribute harus berjenis: :values.',
    'file' => ':attribute harus berupa berkas.',

    'min' => [
        'numeric' => ':attribute minimal :min.',
        'file' => 'Ukuran :attribute minimal :min kilobyte.',
        'string' => ':attribute minimal :min karakter.',
        'array' => ':attribute minimal berisi :min item.',
    ],

    'max' => [
        'numeric' => ':attribute maksimal :max.',
        'file' => 'Ukuran :attribute maksimal :max kilobyte.',
        'string' => ':attribute maksimal :max karakter.',
        'array' => ':attribute maksimal berisi :max item.',
    ],

    'password' => [
        'letters' => ':attribute harus mengandung minimal satu huruf.',
        'mixed' => ':attribute harus mengandung minimal satu huruf besar dan satu huruf kecil.',
        'numbers' => ':attribute harus mengandung minimal satu angka.',
        'symbols' => ':attribute harus mengandung minimal satu karakter khusus.',
        'uncompromised' => ':attribute ini pernah bocor pada kebocoran data. Pilih kata sandi lain.',
    ],

    'attributes' => [
        'name' => 'Nama',
        'email' => 'Email',
        'password' => 'Password',
        'role' => 'Role',
        'dept_code' => 'Kode departemen',
    ],

];
