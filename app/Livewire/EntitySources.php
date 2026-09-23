<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\Entity;
use App\Models\EntityDataSource;
use App\Models\PairingCode;
use App\Models\Period;
use App\Support\Bsc\Consolidation;
use App\Support\Bsc\Sources\EntitySourceFactory;
use App\Support\Bsc\Sources\EntitySourceSettings;
use App\Support\EntityContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

/**
 * Sumber Data Entitas (khusus pemasangan holding).
 *
 * Di sini admin holding menetapkan DI MANA data tiap entitas berada — alamat API
 * beserta kuncinya, atau nama database — tanpa perlu menyunting .env di server.
 * Kunci API disimpan terenkripsi dan tidak pernah ditampilkan utuh lagi.
 */
class EntitySources extends Component
{
    use AuthorizesWrites;

    /** Entitas yang sedang disunting. */
    public ?int $editingId = null;

    public string $driver = EntityDataSource::LOKAL;

    public string $apiUrl = '';

    public string $apiKey = '';

    public string $databaseName = '';

    /** Kredensial baca-saja khusus entitas ini (kosong = kredensial aplikasi ini). */
    public string $dbHost = '';

    public string $dbPort = '';

    public string $dbUsername = '';

    public string $dbPassword = '';

    public bool $punyaSandiDb = false;

    /** Kode pendaftaran yang baru diterbitkan — ditampilkan sekali. */
    public ?string $kodePendaftaran = null;

    public ?int $kodeUntuk = null;

    /** Kunci lama dipertahankan bila kolomnya dibiarkan kosong. */
    public bool $punyaKunci = false;

    public function mount(): void
    {
        $this->ensureHoldingUser();
    }

    private function ensureHoldingUser(): void
    {
        $user = auth()->user();

        abort_unless($user && app(EntityContext::class)->isHoldingUser($user), 403,
            'Pengaturan sumber data hanya untuk pengguna level holding.');
    }

    public function edit(int $entityId): void
    {
        $this->ensureHoldingUser();

        $entitas = Entity::findOrFail($entityId);
        $baris = app(EntitySourceSettings::class)->record($entitas);
        $env = app(EntitySourceSettings::class)->for($entitas);

        $this->editingId = $entitas->id;
        // Belum pernah diatur di layar: isian diawali nilai dari .env agar tinggal disimpan.
        $this->driver = $baris?->driver ?? $env['driver'];
        $this->apiUrl = (string) ($baris?->api_url ?? $env['api_url'] ?? '');
        $this->databaseName = (string) ($baris?->database_name ?? $env['database'] ?? '');
        $this->punyaKunci = (bool) ($baris?->api_key ?? $env['api_key'] ?? null);
        $this->apiKey = '';
        $this->dbHost = (string) ($baris?->db_host ?? '');
        $this->dbPort = (string) ($baris?->db_port ?? '');
        $this->dbUsername = (string) ($baris?->db_username ?? '');
        $this->punyaSandiDb = (bool) $baris?->db_password;
        $this->dbPassword = '';
        $this->resetErrorBag();
    }

    /**
     * Terbitkan kode pendaftaran untuk satu entitas. Admin entitas menempelnya di
     * aplikasinya, lalu aplikasi entitas mengirim alamat & kuncinya sendiri ke sini —
     * di holding tidak ada kunci yang perlu diketik.
     */
    public function issuePairingCode(int $entityId): void
    {
        $this->ensureHoldingUser();

        if ($this->lacksPermission('manage consolidation')) {
            return;
        }

        $entitas = Entity::findOrFail($entityId);
        [, $kode] = PairingCode::issue($entitas);

        $this->kodePendaftaran = $kode;
        $this->kodeUntuk = $entitas->id;
        session()->flash('message', 'Kode pendaftaran '.$entitas->name.' dibuat; berlaku '.PairingCode::MASA_BERLAKU.' menit.');
    }

    public function hidePairingCode(): void
    {
        $this->kodePendaftaran = null;
        $this->kodeUntuk = null;
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->apiKey = '';
        $this->dbPassword = '';
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->ensureHoldingUser();

        if ($this->lacksPermission('manage consolidation')) {
            return;
        }

        $entitas = Entity::findOrFail($this->editingId);
        $lama = app(EntitySourceSettings::class)->record($entitas);

        $this->validate([
            'driver' => ['required', Rule::in([EntityDataSource::LOKAL, EntityDataSource::DATABASE, EntityDataSource::API])],
            'apiUrl' => [Rule::requiredIf($this->driver === EntityDataSource::API), 'nullable', 'url', 'max:255'],
            'databaseName' => [Rule::requiredIf($this->driver === EntityDataSource::DATABASE), 'nullable', 'string', 'max:100'],
            'dbHost' => ['nullable', 'string', 'max:255'],
            'dbPort' => ['nullable', 'numeric'],
            'dbUsername' => ['nullable', 'string', 'max:100'],
            'dbPassword' => ['nullable', 'string', 'max:255'],
            // Kunci hanya wajib saat alamat API pertama kali diisi.
            'apiKey' => [Rule::requiredIf($this->driver === EntityDataSource::API && ! $this->punyaKunci), 'nullable', 'string', 'max:255'],
        ], [], [
            'driver' => 'sumber data', 'apiUrl' => 'alamat API', 'databaseName' => 'nama database', 'apiKey' => 'kunci API',
        ]);

        $nilai = [
            'driver' => $this->driver,
            'api_url' => $this->driver === EntityDataSource::API ? rtrim($this->apiUrl, '/') : null,
            'database_name' => $this->driver === EntityDataSource::DATABASE ? $this->databaseName : null,
            'db_host' => $this->driver === EntityDataSource::DATABASE ? ($this->dbHost ?: null) : null,
            'db_port' => $this->driver === EntityDataSource::DATABASE ? ($this->dbPort ?: null) : null,
            'db_username' => $this->driver === EntityDataSource::DATABASE ? ($this->dbUsername ?: null) : null,
            'updated_by' => auth()->id(),
            'last_status' => null,
            'last_message' => null,
            'last_checked_at' => null,
        ];

        // Kata sandi database: dibiarkan kosong = sandi lama dipakai.
        if ($this->driver !== EntityDataSource::DATABASE) {
            $nilai['db_password'] = null;
        } elseif ($this->dbPassword !== '') {
            $nilai['db_password'] = $this->dbPassword;
        } elseif ($lama?->db_password) {
            $nilai['db_password'] = $lama->db_password;
        }

        if ($this->driver !== EntityDataSource::API) {
            $nilai['api_key'] = null;
        } elseif ($this->apiKey !== '') {
            $nilai['api_key'] = $this->apiKey;
        } elseif ($lama?->api_key) {
            $nilai['api_key'] = $lama->api_key; // kolom dibiarkan kosong = kunci lama dipakai
        }

        EntityDataSource::updateOrCreate(['entity_id' => $entitas->id], $nilai);

        app(EntitySourceSettings::class)->forget();
        // Ringkasan lama milik sumber sebelumnya tidak boleh ikut terbawa.
        app(Consolidation::class)->refreshAll();

        $this->editingId = null;
        $this->apiKey = '';
        $this->dbPassword = '';
        session()->flash('message', 'Sumber data '.$entitas->name.' disimpan.');
    }

    /**
     * Uji sambungan ke entitas: memanggil /api/v1/ping miliknya (atau membuka
     * databasenya) dan mencatat hasilnya. Tidak ada angka kinerja yang diambil.
     */
    public function test(int $entityId): void
    {
        $this->ensureHoldingUser();

        if ($this->lacksPermission('manage consolidation')) {
            return;
        }

        $entitas = Entity::findOrFail($entityId);
        $sumber = app(EntitySourceSettings::class)->for($entitas);
        [$status, $pesan] = $this->periksa($entitas, $sumber);

        // Uji hanya MELAPORKAN; ia tidak membuat baris pengaturan. Menuliskan
        // baris di sini akan diam-diam memindahkan sumber dari .env ke layar —
        // dan pada entitas yang belum diatur sama sekali, membuat holding
        // membaca databasenya sendiri lalu menampilkannya sebagai data entitas.
        EntityDataSource::where('entity_id', $entitas->id)->update([
            'last_status' => $status,
            'last_message' => $pesan,
            'last_checked_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        app(EntitySourceSettings::class)->forget();
        session()->flash($status === 'ok' ? 'message' : 'error', $entitas->name.': '.$pesan);
    }

    /**
     * @param  array{driver: string, api_url: ?string, api_key: ?string, database: ?string, origin: string}  $sumber
     * @return array{0: string, 1: string}
     */
    private function periksa(Entity $entitas, array $sumber): array
    {
        if ($sumber['origin'] === EntitySourceSettings::RUSAK) {
            return ['galat', 'kredensial tersimpan tidak dapat dibuka (kunci aplikasi berganti); atur ulang sumber datanya.'];
        }

        if ($sumber['driver'] === EntityDataSource::API && $sumber['api_url']) {
            try {
                $respons = Http::withHeaders(array_filter(['X-API-KEY' => $sumber['api_key']]))
                    ->acceptJson()
                    ->withoutRedirecting()
                    ->timeout((int) config('bsc.api_timeout', 8))
                    ->get(rtrim((string) $sumber['api_url'], '/').'/api/v1/ping');

                if ($respons->status() === 401 || $respons->status() === 403) {
                    return ['galat', 'kunci API ditolak server entitas ('.$respons->status().').'];
                }

                if ($respons->failed()) {
                    return ['galat', 'server entitas menjawab '.$respons->status().'.'];
                }

                $kode = (string) $respons->json('data.entity_code');

                if ($kode !== $entitas->code) {
                    return ['galat', 'alamat itu melayani entitas '.($kode ?: 'tidak dikenal').', bukan '.$entitas->code.'.'];
                }

                return ['ok', 'tersambung ke '.$respons->json('data.entity').' (versi '.$respons->json('data.version').').'];
            } catch (Throwable $e) {
                report($e);

                return ['galat', 'server entitas tidak dapat dihubungi.'];
            }
        }

        if ($sumber['driver'] === EntityDataSource::DATABASE && $sumber['database']) {
            $ringkasan = app(EntitySourceFactory::class)->for($entitas)->summary($entitas, $this->period());

            return $ringkasan->ok()
                ? ['ok', 'database '.$sumber['database'].' terbaca.']
                : ['galat', (string) $ringkasan->message];
        }

        // Belum diatur pada pemasangan holding yang ketat: jangan dilaporkan "ok",
        // karena tidak ada satu pun data entitas yang benar-benar terbaca.
        if ($sumber['origin'] === 'tidak diatur' && config('bsc.require_entity_sources')
            && app(EntityContext::class)->isHoldingMode()) {
            return ['galat', 'sumber data belum diatur; holding tidak membaca entitas ini.'];
        }

        return ['ok', 'memakai database aplikasi ini (lokal).'];
    }

    /**
     * Selesaikan pendaftaran mandiri: alamat yang benar ditandai tersambung.
     *
     * Dijalankan sebagai permintaan TERSENDIRI (wire:init), sesudah halaman
     * tampil — bukan di tengah render — supaya aplikasi entitas tidak sedang
     * sibuk melayani pendaftarannya sendiri saat dipanggil balik, dan supaya
     * halaman ini tidak pernah mengubah data hanya karena dibuka.
     *
     * Yang gagal TIDAK dihapus dan kodenya TIDAK dihidupkan kembali: satu gangguan
     * jaringan sesaat tidak boleh menghapus pengaturan yang sudah benar, dan kode
     * pendaftaran sekali pakai harus tetap sekali pakai. Selama belum terverifikasi
     * sumbernya memang tidak dibaca (lihat EntitySourceFactory), jadi menahannya
     * sudah cukup aman; admin dapat membuangnya sendiri dari tombol Atur.
     */
    public function verifikasiPendaftaranBaru(): void
    {
        $this->ensureHoldingUser();

        $menunggu = EntityDataSource::with('entity')->where('last_status', 'menunggu')->get();
        $gagal = [];

        foreach ($menunggu as $sumber) {
            if (! $sumber->entity) {
                continue;
            }

            [$status, $pesan] = $this->periksa($sumber->entity, [
                'driver' => $sumber->driver,
                'api_url' => $sumber->api_url,
                'api_key' => $sumber->api_key,
                'database' => $sumber->database_name,
                'db' => [],
                'origin' => 'layar',
            ]);

            if ($status === 'ok') {
                $sumber->forceFill([
                    'last_status' => 'ok',
                    'last_message' => 'didaftarkan sendiri oleh aplikasi entitas; '.$pesan,
                    'last_checked_at' => now(),
                ])->save();

                continue;
            }

            // Belum terbukti: statusnya tetap "menunggu" — sumbernya tidak dibaca,
            // dan percobaan berikutnya terjadi saat halaman ini dibuka lagi.
            $sumber->forceFill([
                'last_message' => 'didaftarkan sendiri oleh aplikasi entitas, belum terverifikasi — '.$pesan,
                'last_checked_at' => now(),
            ])->save();

            $gagal[] = $sumber->entity->name.': '.$pesan;
        }

        if ($gagal !== []) {
            session()->flash('error', 'Pendaftaran belum terverifikasi — '.implode('; ', $gagal));
        }

        if ($menunggu->isNotEmpty()) {
            app(EntitySourceSettings::class)->forget();
            // Sumber yang baru saja terbukti kini boleh dibaca; simpanan
            // "menunggu verifikasi" tidak boleh menutupinya.
            app(Consolidation::class)->refreshAll();
        }
    }

    private function period(): string
    {
        return Period::active();
    }

    public function render()
    {
        $this->ensureHoldingUser();
        $pengaturan = app(EntitySourceSettings::class);

        $kodeAktif = PairingCode::whereNull('used_at')->where('expires_at', '>', now())->get()->keyBy('entity_id');

        $baris = Entity::active()->get()->map(fn (Entity $e) => [
            'entity' => $e,
            'source' => $pengaturan->for($e),
            'record' => $pengaturan->record($e),
            'pairing' => $kodeAktif->get($e->id),
        ]);

        return view('livewire.entity-sources', [
            'baris' => $baris,
            'canManage' => (bool) auth()->user()?->can('manage consolidation'),
            'strict' => (bool) config('bsc.require_entity_sources'),
        ])->layout('layouts.app', ['title' => 'Sumber Data Entitas']);
    }
}
