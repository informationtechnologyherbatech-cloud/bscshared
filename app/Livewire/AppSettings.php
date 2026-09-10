<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Url;
use App\Models\AppSetting;
use App\Models\ApiKey;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

class AppSettings extends Component
{
    use WithFileUploads;

    #[Url]
    public $activeTab = 'identity'; // identity, api, system

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
    public $showKeyId = null; // for reveal
    public $newKeyName = 'Gateway Key';

    public function mount()
    {
        $this->loadIdentity();
    }

    /** Daftar kunci pengaturan identitas yang dikelola halaman ini. */
    private function identityKeys(): array
    {
        return [
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
        ];
    }

    private function loadIdentity()
    {
        foreach ($this->identityKeys() as $key) {
            $this->{$key} = (string) entity($key, config('entity.defaults.'.$key, ''));
        }
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function saveIdentity()
    {
        $this->validate([
            'app_name' => 'required|string|min:3|max:100',
            'app_tagline' => 'nullable|string|max:255',
            'app_year' => 'required|digits:4',
            'app_primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'entity_name' => 'required|string|min:2|max:150',
            'company_name' => 'required|string|min:2|max:200',
            'company_address' => 'nullable|string|max:500',
            'company_phone' => 'nullable|string|max:50',
            'company_email' => 'nullable|email|max:150',
            'company_website' => 'nullable|url|max:200',
            'logoUpload' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'faviconUpload' => 'nullable|image|mimes:png,jpg,jpeg,ico,svg|max:1024',
        ], [], [
            'entity_name' => 'nama entitas',
            'company_name' => 'nama perusahaan',
            'company_address' => 'alamat perusahaan',
            'company_phone' => 'nomor kontak',
            'company_email' => 'email kontak',
            'company_website' => 'situs web',
        ]);

        $values = [];
        foreach ($this->identityKeys() as $key) {
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

        session()->flash('message', 'Identitas entitas & aplikasi berhasil diperbarui!');
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

    public function resetIdentity()
    {
        $defaults = [];
        foreach ($this->identityKeys() as $key) {
            $defaults[$key] = (string) config('entity.defaults.'.$key, '');
        }

        AppSetting::setMany($defaults);
        $this->loadIdentity();
        $this->resetValidation();

        session()->flash('message', 'Identitas dikembalikan ke nilai default!');
    }

    public function generateApiKey()
    {
        $this->validate(['newKeyName' => 'required|string|min:3|max:100']);
        $raw = 'bsc_live_' . Str::random(32);
        ApiKey::create([
            'name' => $this->newKeyName,
            'key' => $raw,
            'is_active' => true,
        ]);

        // Deactivate old keys (keep history but only one active)
        ApiKey::where('key', '!=', $raw)->update(['is_active' => false]);

        $this->newKeyName = 'Gateway Key';
        session()->flash('message', 'Kunci API baru berhasil digenerate!');
    }

    public function toggleKey($id)
    {
        $k = ApiKey::findOrFail($id);
        $k->update(['is_active' => !$k->is_active]);
        session()->flash('message', 'Status kunci ' . $k->name . ' diubah!');
    }

    public function deleteKey($id)
    {
        $k = ApiKey::findOrFail($id);
        // Guard: at least one active key must remain
        if ($k->is_active && ApiKey::where('is_active', true)->count() <= 1) {
            session()->flash('error', 'Tidak dapat menghapus kunci aktif terakhir!');
            return;
        }
        $k->delete();
        session()->flash('message', 'Kunci ' . $k->name . ' dihapus!');
    }

    public function revealKey($id)
    {
        $this->showKeyId = $this->showKeyId === $id ? null : $id;
    }

    public function render()
    {
        $apiKeys = ApiKey::latest()->get();
        $logoPath = AppSetting::getValue('app_logo', '');
        $faviconPath = AppSetting::getValue('app_favicon', '');

        // System info
        $systemInfo = [
            'Versi Aplikasi' => app_version(),
            'PHP Version' => PHP_VERSION,
            'Laravel Version' => app()->version(),
            'App Env' => config('app.env'),
            'App Debug' => config('app.debug') ? 'true' : 'false',
            'App URL' => config('app.url'),
            'Database Driver' => config('database.default') . ' (' . DB::connection()->getDriverName() . ')',
            'Database Name' => DB::connection()->getDatabaseName(),
            'Cache Store' => config('cache.default'),
            'Queue Connection' => config('queue.default'),
            'Session Driver' => config('session.driver') . ' (' . config('session.lifetime') . ' menit)',
            'Timezone' => config('app.timezone'),
            'Storage Link' => is_link(public_path('storage')) ? 'OK (linked)' : 'Missing (run storage:link)',
        ];

        return view('livewire.app-settings', [
            'apiKeys' => $apiKeys,
            'logoPath' => $logoPath,
            'faviconPath' => $faviconPath,
            'systemInfo' => $systemInfo,
        ])->layout('layouts.app', ['title' => 'Setting Sistem']);
    }
}
