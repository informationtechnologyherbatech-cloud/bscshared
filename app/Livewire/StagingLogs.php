<?php

namespace App\Livewire;

use App\Models\DepartmentObjective;
use App\Models\Period;
use App\Models\StagingLog;
use App\Models\WorkUnit;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Jejak audit seluruh data yang MASUK ke entitas ini.
 *
 * Tiap kiriman — tarikan Odoo, unggahan berkas, payload KPI, pengisian pos akun
 * dari layar Integrasi — meninggalkan satu baris di sini: kapan datang, untuk
 * periode dan unit mana, diterima atau ditolak, dan apa yang berubah. Halaman
 * ini hanya MEMBACA; tidak ada tombol yang menulis data, karena jejak audit yang
 * bisa dikarang isinya tidak lagi menjadi bukti.
 *
 * Yang paling sering dicari di sini: "kiriman tadi sampai atau tidak, dan kalau
 * ditolak, kenapa?" — karena itu penolakan ikut dicatat, bukan hanya
 * keberhasilan.
 */
class StagingLogs extends Component
{
    use WithPagination;

    #[Url]
    public string $cari = '';

    #[Url]
    public string $periode = '';

    #[Url]
    public string $unit = '';

    #[Url]
    public string $status = '';

    public function updated(string $properti): void
    {
        // Menyaring sambil berada di halaman 7 akan menampilkan halaman kosong.
        if (in_array($properti, ['cari', 'periode', 'unit', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function bersihkanSaringan(): void
    {
        $this->cari = '';
        $this->periode = '';
        $this->unit = '';
        $this->status = '';
        $this->resetPage();
    }

    public function render()
    {
        $kueri = StagingLog::query()
            ->when($this->periode !== '', fn ($q) => $q->where('period', $this->periode))
            ->when($this->unit !== '', fn ($q) => $q->where('dept_code', $this->unit))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->cari !== '', function ($q) {
                $kata = '%'.$this->cari.'%';
                $q->where(fn ($w) => $w->where('message', 'like', $kata)->orWhere('idempotency_key', 'like', $kata));
            });

        // Daftar per halaman; log terus bertambah, jadi tidak pernah dimuat semua.
        $logs = (clone $kueri)->latest()->latest('id')->paginate(25);

        return view('livewire.staging-logs', [
            'logs' => $logs,
            'ringkasan' => $this->ringkasan($kueri->count()),
            'units' => WorkUnit::active()->get(),
            'daftarPeriode' => StagingLog::distinct()->orderByDesc('period')->pluck('period'),
            'daftarStatus' => StagingLog::distinct()->orderBy('status')->pluck('status'),
            'adaSaringan' => $this->cari !== '' || $this->periode !== '' || $this->unit !== '' || $this->status !== '',
        ])->layout('layouts.app', ['title' => 'Staging & Audit Log']);
    }

    /**
     * Angka yang benar-benar dapat berubah.
     *
     * Dua ukuran lama dibuang karena secara struktur selalu bernilai 100%:
     * "Control Total Match" membandingkan baris SCORED terhadap seluruh baris
     * padahal hampir semua penulis memakai status itu, dan "Integritas Hash"
     * memeriksa keunikan penanda yang sudah dijamin indeks unik di database.
     * Ukuran yang tidak pernah bergerak tidak memberi tahu apa pun.
     *
     * @return array<string, array{nilai: string, keterangan: string, nada: string}>
     */
    private function ringkasan(int $terlihat): array
    {
        $sepekan = StagingLog::where('created_at', '>=', now()->subDays(7))->count();
        $gagal = StagingLog::where('status', 'ERROR')->where('created_at', '>=', now()->subDays(30))->count();

        $periode = Period::currentPeriod();
        $jumlahObjs = DepartmentObjective::where('period', $periode)->count();
        $terisiObjs = DepartmentObjective::where('period', $periode)->where('actual', '>', 0)->count();

        $terakhir = StagingLog::latest()->latest('id')->first();
        $ambang = (int) config('bsc.stale_after_hours', 26);
        $jam = $terakhir ? (int) abs(Carbon::parse($terakhir->created_at)->diffInHours(now())) : null;

        return [
            'masuk' => [
                'nilai' => (string) $sepekan,
                'keterangan' => 'kiriman dalam 7 hari terakhir'.($terlihat > 0 ? ' · '.$terlihat.' baris terlihat' : ''),
                'nada' => $sepekan > 0 ? 'info' : 'secondary',
            ],
            'gagal' => [
                'nilai' => (string) $gagal,
                'keterangan' => 'ditolak dalam 30 hari terakhir',
                'nada' => $gagal > 0 ? 'danger' : 'success',
            ],
            'kelengkapan' => [
                'nilai' => $jumlahObjs > 0 ? round($terisiObjs / $jumlahObjs * 100, 1).'%' : '—',
                'keterangan' => $jumlahObjs > 0
                    ? $terisiObjs.' dari '.$jumlahObjs.' KPI sudah ada realisasinya · '.$periode
                    : 'belum ada KPI pada periode '.$periode,
                'nada' => 'primary',
            ],
            'terakhir' => [
                'nilai' => $terakhir ? $terakhir->created_at->diffForHumans() : 'belum ada',
                'keterangan' => $terakhir
                    ? $terakhir->created_at->translatedFormat('d M Y, H:i').' · '.$terakhir->dept_code
                    : 'belum ada kiriman yang tercatat',
                'nada' => $jam === null ? 'secondary' : ($jam < $ambang ? 'success' : 'warning'),
            ],
        ];
    }
}
