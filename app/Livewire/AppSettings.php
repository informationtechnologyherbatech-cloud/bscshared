<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\ApiAccessLog;
use App\Models\ApiKey;
use App\Models\AppSetting;
use App\Models\Entity;
use App\Support\EntityContext;
use App\Support\Recaptcha;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class AppSettings extends Component
{
    use AuthorizesWrites;
    use WithFileUploads;

    #[Url]
    public $activeTab = 'identity'; // identity, entity, security, api, system

    // Keamanan — Google reCAPTCHA v2 (kotak centang)
    public $recaptcha_enabled = false;

    public $recaptcha_site_key = '';

    /** Dibiarkan kosong berarti "jangan ubah secret key yang tersimpan". */
    public $recaptcha_secret_key = '';

    // Identitas aplikasi
    public $app_name = '';

    public $app_tagline = '';

    public $app_year = '';

    public $app_primary_color = '#17a2b8';

    public $logoUpload;

    public $faviconUpload;

    // Identitas entitas pengguna aplikasi
    public $entity_name = '';

    public $company_name = '';

    public $company_address = '';

    public $company_phone = '';

    public $company_email = '';

    public $company_website = '';

    // API Key fields
    /** Kunci yang baru dibuat — ditampilkan SEKALI lalu hilang dari ingatan. */
    public ?string $kunciBaru = null;

    public $newKeyName = 'Gateway Key';

    /** Daftar IP holding yang boleh memakai kunci (kosong = dari mana saja). */
    public string $newKeyIps = '';

    /** Tanggal kedaluwarsa kunci (kosong = tanpa batas). */
    public string $newKeyExpires = '';

    /** Pendaftaran mandiri ke holding: alamat holding & kode pendaftarannya. */
    public string $holdingUrl = '';

    public string $holdingCode = '';

    public function mount()
    {
        $this->loadIdentity();
        $this->loadSecurity();
        $this->activeTab = $this->firstAllowedTab($this->activeTab);
    }

    /** Izin yang dibutuhkan untuk membuka tiap tab. */
    private function tabPermissions(): array
    {
        return [
            'identity' => 'manage settings',
            'entity' => 'manage settings',
            'security' => 'manage settings',
            'api' => 'manage apikey',
            'system' => 'view systeminfo',
        ];
    }

    public function canOpenTab(string $tab): bool
    {
        $permission = $this->tabPermissions()[$tab] ?? null;

        return $permission !== null && auth()->user()?->can($permission);
    }

    /**
     * Tab yang diminta bila boleh dibuka; bila tidak, tab pertama yang boleh.
     */
    private function firstAllowedTab(string $requested): string
    {
        if ($this->canOpenTab($requested)) {
            return $requested;
        }

        foreach (array_keys($this->tabPermissions()) as $tab) {
            if ($this->canOpenTab($tab)) {
                return $tab;
            }
        }

        return $requested;
    }

    private function loadSecurity(): void
    {
        $recaptcha = app(Recaptcha::class);

        $this->recaptcha_enabled = $recaptcha->toggledOn();
        $this->recaptcha_site_key = (string) $recaptcha->siteKey();
        $this->recaptcha_secret_key = '';
    }

    /**
     * Simpan pengaturan reCAPTCHA. Secret key hanya ditimpa bila kolomnya diisi,
     * dan disimpan terenkripsi — nilainya tidak pernah dikirim balik ke peramban.
     */
    public function saveSecurity()
    {
        if ($this->lacksPermission('manage settings')) {
            return;
        }

        $recaptcha = app(Recaptcha::class);
        $secretTersimpan = $recaptcha->secretKey() !== null;

        $this->validate([
            'recaptcha_enabled' => 'boolean',
            'recaptcha_site_key' => [$this->recaptcha_enabled ? 'required' : 'nullable', 'string', 'max:255'],
            'recaptcha_secret_key' => [
                $this->recaptcha_enabled && ! $secretTersimpan ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
        ], [], [
            'recaptcha_site_key' => 'site key',
            'recaptcha_secret_key' => 'secret key',
        ]);

        AppSetting::setValue(Recaptcha::ENABLED_KEY, $this->recaptcha_enabled ? '1' : '0');
        AppSetting::setValue(Recaptcha::SITE_KEY, trim($this->recaptcha_site_key));

        if (trim($this->recaptcha_secret_key) !== '') {
            AppSetting::setSecret(Recaptcha::SECRET_KEY, trim($this->recaptcha_secret_key));
        }

        $this->recaptcha_secret_key = '';

        session()->flash('message', $this->recaptcha_enabled
            ? 'reCAPTCHA diaktifkan pada halaman login.'
            : 'reCAPTCHA dinonaktifkan.');
    }

    /** Hapus secret key yang tersimpan sekaligus mematikan reCAPTCHA. */
    public function clearRecaptchaSecret()
    {
        if ($this->lacksPermission('manage settings')) {
            return;
        }

        AppSetting::setSecret(Recaptcha::SECRET_KEY, null);
        AppSetting::setValue(Recaptcha::ENABLED_KEY, '0');

        $this->loadSecurity();

        session()->flash('message', 'Secret key reCAPTCHA dihapus dan reCAPTCHA dimatikan.');
    }

    /** Kunci tab Identitas Aplikasi (branding). */
    private function appKeys(): array
    {
        return config('entity.app_keys');
    }

    /** Kunci tab Entitas (perusahaan pemilik data). */
    private function entityKeys(): array
    {
        return config('entity.entity_keys');
    }

    /** Daftar kunci pengaturan identitas yang dikelola halaman ini. */
    private function identityKeys(): array
    {
        return array_merge($this->appKeys(), $this->entityKeys());
    }

    private function loadIdentity()
    {
        foreach ($this->identityKeys() as $key) {
            $this->{$key} = (string) entity($key, config('entity.defaults.'.$key, ''));
        }
    }

    public function switchTab($tab)
    {
        $this->activeTab = $this->firstAllowedTab((string) $tab);
    }

    /** activeTab dapat diubah langsung dari peramban — tetap disaring izinnya. */
    public function updatedActiveTab($tab): void
    {
        $this->activeTab = $this->firstAllowedTab((string) $tab);
    }

    /** Tab Entitas: identitas perusahaan pemilik data. */
    public function saveEntity()
    {
        if ($this->lacksPermission('manage settings')) {
            return;
        }

        $this->validate([
            'entity_name' => 'required|string|min:2|max:150',
            'company_name' => 'required|string|min:2|max:200',
            'company_address' => 'nullable|string|max:500',
            'company_phone' => 'nullable|string|max:50',
            'company_email' => 'nullable|email|max:150',
            // url:http,https menutup URL berskema javascript: yang lolos
            // validasi url biasa dan menjadi XSS saat dipasang di atribut href.
            'company_website' => 'nullable|url:http,https|max:200',
        ], [], [
            'entity_name' => 'nama entitas',
            'company_name' => 'nama perusahaan',
            'company_address' => 'alamat perusahaan',
            'company_phone' => 'nomor kontak',
            'company_email' => 'email kontak',
            'company_website' => 'situs web',
        ]);

        $values = [];
        foreach ($this->entityKeys() as $key) {
            $values[$key] = trim((string) $this->{$key});
        }
        AppSetting::setMany($values);

        session()->flash('message', 'Identitas entitas berhasil diperbarui!');
    }

    /** Tab Identitas Aplikasi: nama aplikasi, tagline, warna, logo & favicon. */
    public function saveApp()
    {
        if ($this->lacksPermission('manage settings')) {
            return;
        }

        $this->validate([
            'app_name' => 'required|string|min:3|max:100',
            'app_tagline' => 'nullable|string|max:255',
            'app_year' => 'required|digits:4',
            'app_primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            // SVG sengaja tidak diterima: berkas SVG dapat memuat <script> dan
            // menjadi stored XSS ketika dibuka langsung dari /storage.
            'logoUpload' => [
                'nullable', 'image', 'mimes:png,jpg,jpeg,webp',
                'mimetypes:image/png,image/jpeg,image/webp', 'max:2048',
            ],
            'faviconUpload' => [
                'nullable', 'file', 'mimes:png,webp,ico',
                'mimetypes:image/png,image/webp,image/x-icon,image/vnd.microsoft.icon',
                'max:1024',
            ],
        ], [], [
            'app_name' => 'nama aplikasi',
            'app_year' => 'tahun',
            'app_primary_color' => 'warna primary',
        ]);

        $values = [];
        foreach ($this->appKeys() as $key) {
            $values[$key] = trim((string) $this->{$key});
        }
        AppSetting::setMany($values);

        if ($this->logoUpload) {
            $this->replaceBrandingFile('app_logo', $this->logoUpload->store('branding', 'public'));
        }
        if ($this->faviconUpload) {
            $this->replaceBrandingFile('app_favicon', $this->faviconUpload->store('branding', 'public'));
        }

        $this->logoUpload = null;
        $this->faviconUpload = null;

        session()->flash('message', 'Identitas aplikasi berhasil diperbarui!');
    }

    /** Simpan berkas branding baru dan hapus berkas lama agar disk tidak menumpuk. */
    private function replaceBrandingFile(string $key, string $newPath): void
    {
        $oldPath = AppSetting::getValue($key);

        if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        AppSetting::setValue($key, $newPath);
    }

    public function resetApp()
    {
        $this->resetKeys($this->appKeys(), 'Identitas aplikasi dikembalikan ke nilai default!');
    }

    /** Kembali ke identitas entitas instalasi (BSC_DEFAULT_ENTITY / BSC_HOLDING_MODE). */
    public function resetEntity()
    {
        $this->resetKeys($this->entityKeys(), 'Identitas entitas dikembalikan sesuai .env: '.config('entity.defaults.company_name').'.');
    }

    private function resetKeys(array $keys, string $pesan): void
    {
        if ($this->lacksPermission('manage settings')) {
            return;
        }

        $defaults = [];
        foreach ($keys as $key) {
            $defaults[$key] = (string) config('entity.defaults.'.$key, '');
        }

        AppSetting::setMany($defaults);
        $this->loadIdentity();
        $this->resetValidation();

        session()->flash('message', $pesan);
    }

    public function generateApiKey()
    {
        if ($this->lacksPermission('manage apikey')) {
            return;
        }

        $this->validate([
            'newKeyName' => 'required|string|min:3|max:100',
            // Daftar IP dipisah koma; boleh alamat persis atau rentang CIDR (10.8.0.0/16).
            'newKeyIps' => ['nullable', 'string', 'max:255', 'regex:/^[0-9a-fA-F:.\/,\s]+$/'],
            'newKeyExpires' => ['nullable', 'date', 'after:today'],
        ], [
            'newKeyIps.regex' => 'Daftar IP hanya boleh berisi alamat IP, rentang CIDR, dan koma.',
            'newKeyExpires.after' => 'Tanggal kedaluwarsa harus setelah hari ini.',
        ], ['newKeyName' => 'nama kunci', 'newKeyIps' => 'daftar IP', 'newKeyExpires' => 'kedaluwarsa']);

        // Kunci utuh hanya dikembalikan sekali di sini; yang tersimpan sidik jarinya.
        [, $kunci] = ApiKey::issue(
            $this->newKeyName,
            strtoupper((string) config('bsc.default_entity')) ?: null,
            $this->newKeyIps,
            $this->newKeyExpires ?: null,
        );

        // Kunci lama sengaja TIDAK langsung dinonaktifkan: rotasi butuh dua kunci
        // aktif sebentar (pasang kunci baru di holding, baru matikan yang lama).
        $this->kunciBaru = $kunci;
        $this->newKeyName = 'Gateway Key';
        $this->newKeyIps = '';
        $this->newKeyExpires = '';
        session()->flash('message', 'Kunci API baru dibuat. Salin sekarang — kunci ini tidak dapat ditampilkan lagi.');
    }

    public function toggleKey($id)
    {
        if ($this->lacksPermission('manage apikey')) {
            return;
        }

        $k = ApiKey::findOrFail($id);
        $k->update(['is_active' => ! $k->is_active]);
        session()->flash('message', 'Status kunci '.$k->name.' diubah!');
    }

    public function deleteKey($id)
    {
        if ($this->lacksPermission('manage apikey')) {
            return;
        }

        $k = ApiKey::findOrFail($id);
        // Guard: at least one active key must remain
        if ($k->is_active && ApiKey::where('is_active', true)->count() <= 1) {
            session()->flash('error', 'Tidak dapat menghapus kunci aktif terakhir!');

            return;
        }
        $k->delete();
        session()->flash('message', 'Kunci '.$k->name.' dihapus!');
    }

    /**
     * Daftarkan pemasangan ini ke holding memakai kode pendaftaran dari sana.
     *
     * Aplikasi ini membuat kunci APInya sendiri lalu mengirimkannya ke holding,
     * sehingga di holding tidak ada kunci yang perlu diketik. Kunci tetap hanya
     * tersimpan sebagai sidik jari di sini.
     */
    public function daftarKeHolding(): void
    {
        if ($this->lacksPermission('manage apikey')) {
            return;
        }

        if (app(EntityContext::class)->isHoldingMode()) {
            session()->flash('error', 'Pemasangan ini holding; yang mendaftar adalah aplikasi entitas.');

            return;
        }

        $this->validate([
            'holdingUrl' => ['required', 'url', 'max:255'],
            'holdingCode' => ['required', 'string', 'max:40'],
        ], [], ['holdingUrl' => 'alamat holding', 'holdingCode' => 'kode pendaftaran']);

        // Kunci API yang baru dibuat dikirim LEWAT alamat ini, jadi alamatnya
        // harus terenkripsi — kecuali saat holding dan entitas berada di mesin
        // yang sama (pengembangan).
        $tuanRumah = (string) parse_url($this->holdingUrl, PHP_URL_HOST);
        $lokal = in_array($tuanRumah, ['127.0.0.1', 'localhost', '::1'], true);

        if (parse_url($this->holdingUrl, PHP_URL_SCHEME) !== 'https' && ! $lokal && ! app()->environment(['local', 'testing'])) {
            session()->flash('error', 'Alamat holding harus HTTPS; kunci API dikirim melalui alamat itu.');

            return;
        }

        $kodeEntitas = strtoupper((string) config('bsc.default_entity'));
        [$kunci, $utuh] = ApiKey::issue('Holding '.$tuanRumah, $kodeEntitas);

        try {
            $respons = Http::acceptJson()
                ->withoutRedirecting()
                ->timeout((int) config('bsc.api_timeout', 8))
                ->post(rtrim($this->holdingUrl, '/').'/api/v1/pairing', [
                    'code' => trim($this->holdingCode),
                    'entity_code' => $kodeEntitas,
                    'entity_url' => rtrim((string) config('app.url'), '/'),
                    'api_key' => $utuh,
                ]);
        } catch (\Throwable $e) {
            report($e);
            $kunci->delete();
            session()->flash('error', 'Holding tidak dapat dihubungi dari sini. Periksa alamatnya.');

            return;
        }

        // Hanya 2xx yang berarti diterima. `failed()` membiarkan 3xx lewat —
        // pengalihan (mis. alamat http yang dibelokkan ke https) akan terbaca
        // sebagai berhasil padahal holding tidak pernah menerima kuncinya.
        if (! $respons->successful()) {
            // Kunci yang gagal dipakai langsung dibuang, jangan menumpuk.
            $kunci->delete();

            $sebab = $respons->redirect()
                ? 'alamat holding mengalihkan ke '.($respons->header('Location') ?: 'alamat lain').'; pakai alamat yang sebenarnya'
                : (string) ($respons->json('message') ?? 'status '.$respons->status());

            session()->flash('error', 'Pendaftaran ditolak holding: '.$sebab.'.');

            return;
        }

        $this->holdingCode = '';
        session()->flash('message', (string) ($respons->json('data.message') ?? 'Terhubung ke holding.').' Kunci dibuat & dikirim otomatis — tidak perlu disalin.');
    }

    /** Tutup tampilan kunci yang baru dibuat. */
    public function hideNewKey(): void
    {
        $this->kunciBaru = null;
    }

    public function render()
    {
        $apiKeys = ApiKey::latest()->get();
        // Jejak akses terakhir: memperlihatkan percobaan yang ditolak, bukan hanya yang berhasil.
        $jejakApi = ApiAccessLog::latest('id')->limit(15)->get();
        $recaptchaSecretTersimpan = app(Recaptcha::class)->secretKey() !== null;
        $logoPath = AppSetting::getValue('app_logo', '');

        // System info
        $systemInfo = [
            'Versi Aplikasi' => app_version(),
            'PHP Version' => PHP_VERSION,
            'Laravel Version' => app()->version(),
            'App Env' => config('app.env'),
            'App Debug' => config('app.debug') ? 'true' : 'false',
            'App URL' => config('app.url'),
            'Database Driver' => config('database.default').' ('.DB::connection()->getDriverName().')',
            'Database Name' => DB::connection()->getDatabaseName(),
            'Cache Store' => config('cache.default'),
            'Queue Connection' => config('queue.default'),
            'Session Driver' => config('session.driver').' ('.config('session.lifetime').' menit)',
            'Timezone' => config('app.timezone'),
            'Storage Link' => is_link(public_path('storage')) ? 'OK (linked)' : 'Missing (run storage:link)',
        ];

        return view('livewire.app-settings', [
            'apiKeys' => $apiKeys,
            'jejakApi' => $jejakApi,
            'recaptchaSecretTersimpan' => $recaptchaSecretTersimpan,
            'logoPath' => $logoPath,
            'systemInfo' => $systemInfo,
            'installation' => [
                'holding' => (bool) config('bsc.holding_mode'),
                'code' => config('bsc.default_entity'),
                'entity' => Entity::configuredDefault(),
                'defaults' => ['entity_name' => config('entity.defaults.entity_name'), 'company_name' => config('entity.defaults.company_name')],
                'entities' => Entity::active()->get(),
            ],
        ])->layout('layouts.app', ['title' => 'Setting Sistem']);
    }
}
