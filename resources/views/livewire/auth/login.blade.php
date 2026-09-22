<div class="login-shell">
    <div class="login-full">
        {{-- Brand Zone — fullscreen left 42% --}}
        <div class="login-brand">
            <div class="text-center mb-4">
                @if(entity_logo())
                    <img src="{{ entity_logo() }}" alt="{{ entity_name() }}" style="max-height:72px; max-width:180px; object-fit:contain;">
                @else
                    <div class="icon-circle"><i class="fas fa-chart-line"></i></div>
                @endif
            </div>
            <h3 class="text-center mb-1">{{ app_display_name() }}</h3>
            <p class="text-center brand-subtitle mb-4">{{ company_name() }}</p>
            <div class="brand-quote">
                <p>Satu sumber kebenaran skor kinerja — dari revenue puncak hingga action plan mitigasi, dapat ditelusuri dalam ≤3 klik.</p>
            </div>
        </div>

        {{-- Action Zone — fullscreen right 58% --}}
        <div class="login-form-wrap">
            <div class="mb-3">
                <h4 class="mb-1">Masuk ke Akun Anda</h4>
                <p class="mb-0" style="font-size:14px; line-height:1.5; color:var(--c-on-variant);">Gunakan email & password sesuai role Anda. Sistem akan mengarahkan otomatis.</p>
            </div>

            @if(session()->has('status'))
                <div class="alert alert-success py-2"><i class="fas fa-check-circle mr-1"></i> {{ session('status') }}</div>
            @endif
            @if(session()->has('error'))
                <div class="alert alert-danger py-2"><i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}</div>
            @endif
            @if(session()->has('expired_msg'))
                <div class="alert alert-warning py-2"><i class="fas fa-clock mr-1"></i> {{ session('expired_msg') }}</div>
            @endif

            <form wire:submit.prevent="login" novalidate>
                <div class="form-group mb-3">
                    <label class="form-label-premium">Email <span style="color:var(--c-error)">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                        <input type="email" wire:model="email" class="form-control @error('email') is-invalid @enderror" placeholder="nama@perusahaan.co.id" autofocus autocomplete="email">
                    </div>
                    @error('email') <span class="invalid-feedback d-block" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>

                <div class="form-group mb-3" x-data="{ show: false }">
                    <label class="form-label-premium">Password <span style="color:var(--c-error)">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                        <input :type="show ? 'text' : 'password'" wire:model="password" class="form-control @error('password') is-invalid @enderror" placeholder="••••••••" autocomplete="current-password">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary"
                                    @click="show = ! show"
                                    :title="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-pressed="show ? 'true' : 'false'">
                                <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                    </div>
                    @error('password') <span class="invalid-feedback d-block" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>

                @if($recaptchaEnabled)
                    <div class="form-group mb-3">
                        <div wire:ignore>
                            <div class="g-recaptcha"
                                 data-sitekey="{{ $recaptchaSiteKey }}"
                                 data-callback="bscRecaptchaSolved"
                                 data-expired-callback="bscRecaptchaExpired"
                                 data-error-callback="bscRecaptchaExpired"></div>
                        </div>
                        @error('recaptchaToken')
                            <span class="invalid-feedback d-block" style="font-size:12px;">{{ $message }}</span>
                        @enderror
                    </div>
                @endif

                <div class="d-flex align-items-center justify-content-between" style="margin-bottom:16px;">

                    <small style="font-size:12px; font-weight:500; color:var(--c-on-variant);">Lupa password? Hubungi Super Admin</small>
                </div>

                <button type="submit" wire:loading.attr="disabled" class="btn btn-teal btn-block">
                    <span wire:loading.remove><i class="fas fa-arrow-right mr-1"></i> Masuk</span>
                    <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Memproses...</span>
                </button>
            </form>

            <div class="divider-hairline my-4"></div>
            <div class="text-center" style="font-size:12px; color:var(--c-on-variant);">
                {{ entity_copyright() }} · {{ app_display_name() }} {{ app_version() }}
                @if(entity('company_email'))
                    <br><a href="mailto:{{ entity('company_email') }}">{{ entity('company_email') }}</a>
                @endif
            </div>
        </div>
    </div>

    @if($recaptchaEnabled)
        {{-- Jembatan antara widget reCAPTCHA dan properti komponen Livewire. --}}
        @script
        <script>
            window.bscRecaptchaSolved = (token) => $wire.set('recaptchaToken', token, false);
            window.bscRecaptchaExpired = () => $wire.set('recaptchaToken', '', false);

            // Token hanya sekali pakai: gambar ulang widget setiap login gagal.
            $wire.on('recaptcha-reset', () => {
                if (window.grecaptcha) {
                    window.grecaptcha.reset();
                }
            });
        </script>
        @endscript
    @endif
</div>
