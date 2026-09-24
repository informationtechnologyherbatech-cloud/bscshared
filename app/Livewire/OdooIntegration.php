<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\AccountMapping;
use App\Models\OdooConnection;
use App\Models\Period;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\Integration\OdooClient;
use App\Support\Bsc\Integration\OdooPuller;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

/**
 * Integrasi Odoo untuk SATU entitas: sambungannya, pemetaan akunnya, dan
 * tarikan datanya.
 *
 * Arahnya menarik — aplikasi ini yang memanggil Odoo, bukan sebaliknya —
 * sehingga tidak ada yang perlu dipasang di sisi Odoo selain satu pengguna
 * yang cukup berhak membaca jurnal. Kunci APInya disimpan terenkripsi dan
 * tidak pernah ditampilkan utuh lagi.
 */
class OdooIntegration extends Component
{
    use AuthorizesWrites;

    /* ── Sambungan ── */
    public string $baseUrl = '';

    public string $databaseName = '';

    /** Perusahaan Odoo yang dibaca; kosong = database itu berisi satu perusahaan. */
    public ?int $companyId = null;

    /** Pilihan perusahaan yang terbaca dari Odoo saat sambungan diuji. */
    public array $daftarPerusahaan = [];

    public string $username = '';

    public string $apiKey = '';

    public bool $isActive = true;

    public bool $fillsRevenue = true;

    public bool $punyaKunci = false;

    /** Panduan langkah di sisi Odoo — dibuka dari ikon "?" pada kartu sambungan. */
    public bool $showPanduan = false;

    /* ── Tarikan ── */
    public string $period = '';

    /* ── Pemetaan ── */
    /** Formulir pemetaan terbuka. Ditandai sendiri, bukan disimpulkan dari isian:
     *  pemetaan BARU justru berisian kosong. */
    public bool $showMapping = false;

    public ?int $editingId = null;

    public string $sourceCode = '';

    public string $sourceName = '';

    public string $postCode = 'PA01';

    public bool $invert = false;

    /** Daftar akun yang baru diambil dari Odoo, untuk dipetakan cepat. */
    public array $akunOdoo = [];

    /**
     * Ditanam di dalam halaman Integrasi & Gateway, bukan berdiri sendiri —
     * judul halamannya dilewati supaya tidak ada dua judul bertumpuk.
     */
    public bool $embedded = false;

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        $this->period = Period::currentPeriod();
        $sambungan = OdooConnection::first();

        if ($sambungan) {
            $this->baseUrl = (string) $sambungan->base_url;
            $this->databaseName = (string) $sambungan->database_name;
            $this->companyId = $sambungan->company_id;
            $this->username = (string) $sambungan->username;
            $this->isActive = (bool) $sambungan->is_active;
            $this->fillsRevenue = (bool) $sambungan->fills_revenue;
            $this->punyaKunci = $sambungan->maskedKey() !== null;
        }
    }

    public function bukaPanduan(): void
    {
        $this->showPanduan = true;
    }

    public function tutupPanduan(): void
    {
        $this->showPanduan = false;
    }

    public function saveConnection(): void
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $this->validate([
            'baseUrl' => ['required', 'url:http,https', 'max:255'],
            'databaseName' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:150'],
            // Kunci hanya wajib saat sambungan pertama kali dibuat.
            'apiKey' => [Rule::requiredIf(! $this->punyaKunci), 'nullable', 'string', 'max:255'],
        ], [], [
            'baseUrl' => 'alamat Odoo', 'databaseName' => 'nama database', 'username' => 'nama pengguna', 'apiKey' => 'kunci API',
        ]);

        $nilai = [
            'base_url' => rtrim($this->baseUrl, '/'),
            'database_name' => $this->databaseName,
            'company_id' => $this->companyId ?: null,
            'company_name' => collect($this->daftarPerusahaan)->firstWhere('id', $this->companyId)['name'] ?? null,
            'username' => $this->username,
            'is_active' => $this->isActive,
            'fills_revenue' => $this->fillsRevenue,
            'updated_by' => auth()->id(),
        ];

        // Kolom kunci dibiarkan kosong = kunci lama dipertahankan.
        if ($this->apiKey !== '') {
            $nilai['api_key'] = $this->apiKey;
        }

        $sambungan = OdooConnection::first();
        $sambungan ? $sambungan->update($nilai) : OdooConnection::create($nilai);

        $this->apiKey = '';
        $this->punyaKunci = true;
        session()->flash('message', 'Sambungan Odoo disimpan.');
    }

    public function testConnection(): void
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $sambungan = OdooConnection::first();

        if (! $sambungan) {
            session()->flash('error', 'Simpan sambungannya lebih dulu.');

            return;
        }

        try {
            $klien = OdooClient::for($sambungan);
            $uid = $klien->login();
            $this->daftarPerusahaan = $klien->companies();
            $akun = $klien->accounts(5);

            $pesan = 'Tersambung ke Odoo (uid '.$uid.'). Contoh akun: '
                .collect($akun)->pluck('code')->implode(', ').'.';

            // Beberapa perusahaan dalam satu database tanpa dipilih salah satunya
            // berarti saldonya akan terjumlah semua — salah, dan diam-diam.
            $perluDipilih = count($this->daftarPerusahaan) > 1 && ! $sambungan->company_id;

            $sambungan->forceFill([
                'last_status' => $perluDipilih ? 'galat' : 'ok',
                'last_message' => $perluDipilih
                    ? 'Database ini memuat '.count($this->daftarPerusahaan).' perusahaan; pilih salah satu lebih dulu.'
                    : 'Sambungan diuji: masuk sebagai uid '.$uid.', bagan akun terbaca.',
                'last_run_at' => now(),
            ])->save();

            if ($perluDipilih) {
                session()->flash('error', 'Tersambung, tetapi database Odoo ini memuat '
                    .count($this->daftarPerusahaan).' perusahaan. Pilih perusahaan entitas ini lalu simpan — '
                    .'tanpa itu saldo semua perusahaan akan terjumlah menjadi satu.');

                return;
            }

            session()->flash('message', $pesan);
        } catch (Throwable $e) {
            $this->catatGagal($sambungan, $e);
        }
    }

    /** Ambil bagan akun dari Odoo supaya pemetaan tinggal memilih, bukan mengetik. */
    public function fetchAccounts(): void
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $sambungan = OdooConnection::first();

        if (! $sambungan) {
            session()->flash('error', 'Simpan sambungannya lebih dulu.');

            return;
        }

        try {
            $this->akunOdoo = OdooClient::for($sambungan)->accounts();
            session()->flash('message', count($this->akunOdoo).' akun terbaca dari Odoo.');
        } catch (Throwable $e) {
            $this->catatGagal($sambungan, $e);
        }
    }

    public function pullNow(OdooPuller $puller): void
    {
        if ($this->lacksPermission('manage integration') || $this->lacksPermission('manage ratios')) {
            return;
        }

        $this->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']], [
            'period.regex' => 'Periode harus berbentuk YYYY-MM.',
        ]);

        $sambungan = OdooConnection::first();

        if (! $sambungan) {
            session()->flash('error', 'Simpan sambungannya lebih dulu.');

            return;
        }

        if (AccountMapping::count() === 0) {
            session()->flash('error', 'Belum ada pemetaan akun; tarikan tidak akan menemukan pos apa pun.');

            return;
        }

        try {
            $hasil = $puller->pull($sambungan, $this->period);

            $sambungan->forceFill([
                'last_status' => $hasil->ok() ? 'ok' : 'galat',
                'last_message' => mb_substr($hasil->message, 0, 500),
                'last_run_at' => now(),
            ])->save();

            session()->flash($hasil->ok() ? 'message' : 'error', $hasil->message);
        } catch (Throwable $e) {
            $this->catatGagal($sambungan, $e);
        }
    }

    private function catatGagal(OdooConnection $sambungan, Throwable $e): void
    {
        report($e);

        $sambungan->forceFill([
            'last_status' => 'galat',
            'last_message' => mb_substr($e->getMessage(), 0, 500),
            'last_run_at' => now(),
        ])->save();

        session()->flash('error', $e->getMessage());
    }

    /* ─────────────────────────────── Pemetaan akun ─────────────────────────────── */

    public function editMapping(?int $id = null, ?string $kode = null, ?string $nama = null): void
    {
        $this->resetErrorBag();
        $this->showMapping = true;
        $petak = $id ? AccountMapping::find($id) : null;

        $this->editingId = $petak?->id;
        $this->sourceCode = $petak->source_code ?? (string) $kode;
        $this->sourceName = $petak->source_name ?? (string) $nama;
        $this->postCode = $petak->post_code ?? 'PA01';
        $this->invert = $petak ? (bool) $petak->invert : AccountMapping::defaultInvert($this->postCode);
    }

    public function updatedPostCode(string $kode): void
    {
        // Pemetaan baru mengikuti kebiasaan pos itu; yang sudah ada tidak diubah
        // diam-diam, karena tiap bagan akun punya tandanya sendiri.
        if (! $this->editingId) {
            $this->invert = AccountMapping::defaultInvert($kode);
        }
    }

    public function saveMapping(): void
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $this->validate([
            'sourceCode' => ['required', 'string', 'max:50'],
            'sourceName' => ['nullable', 'string', 'max:255'],
            'postCode' => ['required', Rule::in(array_keys(AccountPosts::all()))],
        ], [], ['sourceCode' => 'kode akun', 'sourceName' => 'nama akun', 'postCode' => 'pos akun']);

        $kode = strtoupper(trim($this->sourceCode));
        $bentrok = AccountMapping::where('source_code', $kode)
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))->exists();

        if ($bentrok) {
            $this->addError('sourceCode', 'Kode akun '.$kode.' sudah dipetakan.');

            return;
        }

        AccountMapping::updateOrCreate(
            ['id' => $this->editingId],
            [
                'source_code' => $kode,
                'source_name' => $this->sourceName ?: null,
                'post_code' => $this->postCode,
                'invert' => $this->invert,
                'updated_by' => auth()->id(),
            ]
        );

        $this->cancelMapping();
        session()->flash('message', 'Pemetaan akun '.$kode.' disimpan.');
    }

    public function deleteMapping(int $id): void
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        AccountMapping::find($id)?->delete();
        session()->flash('message', 'Pemetaan dihapus.');
    }

    public function cancelMapping(): void
    {
        $this->showMapping = false;
        $this->editingId = null;
        $this->sourceCode = '';
        $this->sourceName = '';
        $this->postCode = 'PA01';
        $this->invert = false;
        $this->resetErrorBag();
    }

    public function render()
    {
        $pemetaan = AccountMapping::orderBy('post_code')->orderBy('source_code')->get();

        return view('livewire.odoo-integration', [
            'sambungan' => OdooConnection::first(),
            'pemetaan' => $pemetaan,
            'posDipetakan' => $pemetaan->pluck('post_code')->unique()->values()->all(),
            'katalogPos' => AccountPosts::all(),
            'canManage' => (bool) auth()->user()?->can('manage integration'),
        ])->layout('layouts.app', ['title' => 'Integrasi Odoo']);
    }
}
