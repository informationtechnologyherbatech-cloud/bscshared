<?php

namespace App\Livewire\Auth;

use App\Support\Recaptcha;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class Login extends Component
{
    public $email = '';
    public $password = '';
    public $remember = false;

    /** Token dari widget reCAPTCHA, diisi oleh JavaScript saat kotak dicentang. */
    public $recaptchaToken = '';

    public function login()
    {
        $this->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:1',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $throttleKey = 'login:'.strtolower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan. Coba lagi dalam '.$seconds.' detik.',
            ]);
        }

        // Verifikasi reCAPTCHA sebelum kredensial diperiksa, sehingga bot tidak
        // dapat memakai halaman login untuk menebak kata sandi.
        $recaptcha = app(Recaptcha::class);

        if ($recaptcha->enabled() && ! $recaptcha->verify($this->recaptchaToken, request()->ip())) {
            RateLimiter::hit($throttleKey, 60);
            $this->resetRecaptcha();

            throw ValidationException::withMessages([
                'recaptchaToken' => 'Verifikasi reCAPTCHA gagal. Silakan centang kembali kotak verifikasi.',
            ]);
        }

        $credentials = [
            'email' => strtolower(trim($this->email)),
            'password' => $this->password,
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($throttleKey, 60);
            $this->resetRecaptcha();

            // Check if user exists but inactive
            $user = \App\Models\User::where('email', strtolower(trim($this->email)))->first();
            if ($user && ! $user->is_active) {
                throw ValidationException::withMessages([
                    'email' => 'Akun Anda telah dinonaktifkan. Hubungi Super Admin.',
                ]);
            }

            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $user = Auth::user();
        // Ensure user still active after login (race)
        if (! $user->is_active) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
            throw ValidationException::withMessages([
                'email' => 'Akun Anda telah dinonaktifkan.',
            ]);
        }

        // Determine redirect by role/permission (FR-15)
        $redirect = $this->resolveRedirectByRole($user);

        // Honor intended url if it exists and user has access
        $intended = session()->pull('url.intended', null);
        if (is_string($intended) && $intended !== '' && $this->isSafeRedirect($intended)) {
            // Basic check: don't redirect to login itself
            if (! str_contains($intended, '/login')) {
                return redirect()->to($intended);
            }
        }

        return redirect()->to($redirect);
    }

    /**
     * Tolak tujuan di luar host aplikasi supaya halaman login tidak bisa
     * dipakai memantulkan pengguna ke situs lain (open redirect).
     */
    private function isSafeRedirect(string $url): bool
    {
        if (str_starts_with($url, '//')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host === null || $host === request()->getHost();
    }

    /**
     * Token reCAPTCHA hanya berlaku sekali pakai, jadi widget harus digambar
     * ulang setiap percobaan login gagal.
     */
    private function resetRecaptcha(): void
    {
        $this->recaptchaToken = '';
        $this->dispatch('recaptcha-reset');
    }

    private function resolveRedirectByRole($user): string
    {
        // Order by responsibility: most specific first
        // Super Admin -> dashboard (full access)
        if ($user->hasRole('Super Admin')) {
            return route('dashboard');
        }
        // Admin FAT -> rasio keuangan (core finance)
        if ($user->hasRole('Admin FAT')) {
            return route('financial-ratios');
        }
        // Admin HRIS -> objectives (mutu & HRIS)
        if ($user->hasRole('Admin HRIS')) {
            return route('department-objectives');
        }
        // Kepala Departemen -> objectives (kelola KPI unit)
        if ($user->hasRole('Kepala Departemen')) {
            if ($user->hasPermissionTo('view dashboard')) {
                return route('dashboard');
            }
            return route('department-objectives');
        }
        // Operator -> objectives
        if ($user->hasRole('Operator')) {
            if ($user->hasPermissionTo('view dashboard')) {
                return route('dashboard');
            }
            return route('department-objectives');
        }
        // Viewer -> dashboard (read-only)
        if ($user->hasRole('Viewer')) {
            return route('dashboard');
        }

        // Fallback by permission
        if ($user->can('view dashboard')) {
            return route('dashboard');
        }
        if ($user->can('manage ratios') || $user->can('view wiring')) {
            return route('financial-ratios');
        }
        if ($user->can('manage objectives')) {
            return route('department-objectives');
        }
        if ($user->can('manage actionplans')) {
            return route('action-plans');
        }
        if ($user->can('view staging')) {
            return route('staging-logs');
        }
        return route('dashboard');
    }

    public function render()
    {
        $recaptcha = app(Recaptcha::class);

        return view('livewire.auth.login', [
            'recaptchaEnabled' => $recaptcha->enabled(),
            'recaptchaSiteKey' => $recaptcha->siteKey(),
        ])->layout('layouts.guest', ['title' => 'Login']);
    }
}
