<?php

namespace App\Livewire\Concerns;

/**
 * Pemeriksaan izin untuk aksi Livewire yang mengubah data.
 *
 * Middleware pada rute hanya menjaga akses HALAMAN. Aksi Livewire dapat
 * dipanggil siapa pun yang berhasil membuka halamannya, sehingga setiap aksi
 * yang menulis data wajib memeriksa izinnya sendiri.
 */
trait AuthorizesWrites
{
    /**
     * Benar bila pengguna tidak memiliki satu pun izin yang disebutkan.
     * Pesan penolakan langsung disiapkan untuk ditampilkan.
     */
    protected function lacksPermission(string ...$permissions): bool
    {
        $user = auth()->user();

        foreach ($permissions as $permission) {
            if ($user?->can($permission)) {
                return false;
            }
        }

        session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang melakukan tindakan ini.');

        return true;
    }
}
