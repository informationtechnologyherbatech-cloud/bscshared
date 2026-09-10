<div class="login-shell">
    <div class="login-full">
        {{-- Brand Zone --}}
        <div class="login-brand">
            <div class="text-center mb-4">
                @if(entity_logo())
                    <img src="{{ entity_logo() }}" alt="{{ entity_name() }}" style="max-height:72px; max-width:180px; object-fit:contain;">
                @else
                    <div class="icon-circle"><i class="fas fa-key"></i></div>
                @endif
            </div>
            <h3 class="text-center mb-1">{{ app_display_name() }}</h3>
            <p class="text-center brand-subtitle mb-4">{{ company_name() }}</p>
            <div class="brand-quote">
                <p>
                    @if($wajibGanti)
                        Kata sandi Anda belum memenuhi standar keamanan. Perbarui sekarang untuk melanjutkan.
                    @else
                        Kata sandi yang kuat melindungi seluruh data kinerja perusahaan.
                    @endif
                </p>
            </div>
        </div>

        {{-- Action Zone --}}
        <div class="login-form-wrap">
            <div class="mb-3">
                <h4 class="mb-1">Ganti Password</h4>
                <p class="mb-0" style="font-size:14px; line-height:1.5; color:var(--c-on-variant);">
                    Masuk sebagai <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).
                </p>
            </div>

            @if($wajibGanti)
                <div class="alert alert-warning" role="alert" style="font-size:13px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Kata sandi Anda wajib diganti sebelum dapat membuka menu mana pun.
                </div>
            @endif

            @if (session()->has('message'))
                <div class="alert alert-success" role="alert" style="font-size:13px;">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
                </div>
            @endif

            <form wire:submit.prevent="updatePassword">
                <div class="form-group mb-3" x-data="{ show: false }">
                    <label class="form-label-premium">Kata Sandi Saat Ini <span style="color:var(--c-error)">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                        <input :type="show ? 'text' : 'password'" wire:model="current_password"
                               class="form-control @error('current_password') is-invalid @enderror"
                               placeholder="••••••••" autocomplete="current-password">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary"
                                    @click="show = ! show"
                                    :title="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-pressed="show ? 'true' : 'false'"
                                    tabindex="-1">
                                <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                    </div>
                    @error('current_password') <span class="invalid-feedback d-block" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>

                <div class="form-group mb-3" x-data="{ show: false, nilai: '' }">
                    <label class="form-label-premium">Kata Sandi Baru <span style="color:var(--c-error)">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-key"></i></span></div>
                        <input :type="show ? 'text' : 'password'" wire:model="password"
                               x-on:input="nilai = $event.target.value"
                               class="form-control @error('password') is-invalid @enderror"
                               placeholder="Kata sandi baru" autocomplete="new-password">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary"
                                    @click="show = ! show"
                                    :title="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-pressed="show ? 'true' : 'false'"
                                    tabindex="-1">
                                <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                    </div>
                    @error('password') <span class="invalid-feedback d-block" style="font-size:12px;">{{ $message }}</span> @enderror

                    <ul class="list-unstyled mt-2 mb-0" style="font-size:12px;" x-show="nilai.length > 0" x-cloak>
                        @foreach($passwordChecklist as $syarat)
                            <li x-data="{ lolos: false }"
                                x-effect="lolos = new RegExp(@js($syarat['regex'])).test(nilai)"
                                :class="lolos ? 'text-success' : 'text-muted'">
                                <i class="fas" :class="lolos ? 'fa-check-circle' : 'fa-circle-notch'"></i>
                                {{ $syarat['label'] }}
                            </li>
                        @endforeach
                    </ul>
                    <small class="d-block mt-1" style="font-size:12px; color:var(--c-on-variant);" x-show="nilai.length === 0">{{ $passwordHint }}</small>
                </div>

                <div class="form-group mb-3" x-data="{ show: false }">
                    <label class="form-label-premium">Ulangi Kata Sandi Baru <span style="color:var(--c-error)">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-redo"></i></span></div>
                        <input :type="show ? 'text' : 'password'" wire:model="password_confirmation"
                               class="form-control" placeholder="Ulangi kata sandi baru" autocomplete="new-password">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary"
                                    @click="show = ! show"
                                    :title="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-pressed="show ? 'true' : 'false'"
                                    tabindex="-1">
                                <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" wire:loading.attr="disabled" class="btn btn-teal btn-block">
                    <span wire:loading.remove><i class="fas fa-save mr-1"></i> Simpan Kata Sandi Baru</span>
                    <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...</span>
                </button>
            </form>

            <div class="divider-hairline my-4"></div>
            <div class="text-center">
                <form method="POST" action="{{ route('logout') }}" class="mb-0">
                    @csrf
                    <button type="submit" class="btn btn-link" style="font-size:12px;">
                        <i class="fas fa-sign-out-alt mr-1"></i> Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
