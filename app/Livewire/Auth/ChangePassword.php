<?php

namespace App\Livewire\Auth;

use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Halaman ganti kata sandi, sekaligus jalan keluar satu-satunya bagi pengguna
 * yang kata sandinya ditandai wajib diganti.
 */
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'max:255', 'confirmed', PasswordPolicy::rule()],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'current_password' => 'Kata sandi saat ini',
            'password' => 'Kata sandi baru',
        ];
    }

    public function updatePassword()
    {
        $this->validate();

        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->reset('current_password');

            $this->addError('current_password', 'Kata sandi saat ini tidak cocok.');

            return;
        }

        if (Hash::check($this->password, $user->password)) {
            $this->addError('password', 'Kata sandi baru harus berbeda dari kata sandi sekarang.');

            return;
        }

        $user->forceFill([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // Kata sandi berganti: perbarui ID sesi agar sesi lama tidak dapat dipakai.
        session()->regenerate();

        $this->reset(['current_password', 'password', 'password_confirmation']);

        session()->flash('message', 'Kata sandi berhasil diperbarui.');

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.change-password', [
            'wajibGanti' => (bool) Auth::user()?->must_change_password,
            'passwordChecklist' => PasswordPolicy::checklist(),
            'passwordHint' => PasswordPolicy::hint(),
        ])->layout('layouts.guest', ['title' => 'Ganti Password']);
    }
}
