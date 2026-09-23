<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Livewire\Concerns\FollowsActivePeriod;
use App\Models\Entity;
use App\Models\IntercompanySale;
use App\Models\Period;
use App\Support\Bsc\Consolidation;
use App\Support\EntityContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Konsolidasi holding Erhanesia Mulia Corpora: skor keempat entitas
 * berdampingan dengan skala yang sama, dan revenue grup setelah eliminasi
 * penjualan antarentitas.
 *
 * Hanya untuk pengguna level holding — pengguna yang terikat satu entitas
 * tidak boleh melihat data entitas lain.
 */
class HoldingConsolidation extends Component
{
    use AuthorizesWrites;
    use FollowsActivePeriod;

    #[Url]
    public string $period = '';

    public ?int $editingId = null;

    public bool $showForm = false;

    /** @var array<string, string> */
    public array $form = [];

    public function mount(): void
    {
        $this->ensureHoldingUser();

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->period)) {
            // Periode aktif di navbar; tanpa periode sama sekali, periode terbaru grup
            // (kueri langsung karena lintas entitas).
            $this->period = Period::list() ? Period::active() : (DB::table('periods')->max('period') ?? now()->format('Y-m'));
        }

        $this->form = $this->blankForm();
    }

    private function ensureHoldingUser(): void
    {
        $user = auth()->user();

        abort_unless($user && app(EntityContext::class)->isHoldingUser($user), 403,
            'Konsolidasi hanya untuk pengguna level holding.');
    }

    /** @return array<string, string> */
    private function blankForm(): array
    {
        return [
            'period' => $this->period,
            'seller_entity_id' => '',
            'buyer_entity_id' => '',
            'planned_amount' => '',
            'actual_amount' => '',
            'notes' => '',
        ];
    }

    public function updatedPeriod(): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->period)) {
            $this->period = now()->format('Y-m');
        }
        $this->shareActivePeriod($this->period);
    }

    public function openCreate(): void
    {
        $this->ensureHoldingUser();
        if ($this->lacksPermission('manage consolidation')) {
            return;
        }

        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = $this->blankForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->ensureHoldingUser();
        if ($this->lacksPermission('manage consolidation')) {
            return;
        }

        $e = IntercompanySale::findOrFail($id);
        $this->resetErrorBag();
        $this->editingId = $e->id;
        $this->form = [
            'period' => $e->period,
            'seller_entity_id' => (string) $e->seller_entity_id,
            'buyer_entity_id' => (string) $e->buyer_entity_id,
            'planned_amount' => $e->planned_amount === null ? '' : (string) $e->planned_amount,
            'actual_amount' => $e->actual_amount === null ? '' : (string) $e->actual_amount,
            'notes' => (string) $e->notes,
        ];
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function save(): void
    {
        $this->ensureHoldingUser();
        if ($this->lacksPermission('manage consolidation')) {
            return;
        }

        $entitas = Entity::pluck('id')->all();

        $data = $this->validate([
            'form.period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'form.seller_entity_id' => ['required', Rule::in($entitas)],
            'form.buyer_entity_id' => ['required', Rule::in($entitas), 'different:form.seller_entity_id'],
            'form.planned_amount' => ['nullable', 'numeric', 'min:0'],
            'form.actual_amount' => ['nullable', 'numeric', 'min:0'],
            'form.notes' => ['nullable', 'string', 'max:255'],
        ], [
            'form.buyer_entity_id.different' => 'Entitas pembeli harus berbeda dari penjual.',
        ], [
            'form.period' => 'periode', 'form.seller_entity_id' => 'entitas penjual',
            'form.buyer_entity_id' => 'entitas pembeli', 'form.planned_amount' => 'rencana',
            'form.actual_amount' => 'realisasi',
        ])['form'];

        if ($data['planned_amount'] === '' && $data['actual_amount'] === '') {
            $this->addError('form.actual_amount', 'Isi rencana atau realisasi penjualan antarentitas.');

            return;
        }

        $duplikat = IntercompanySale::where('period', $data['period'])
            ->where('seller_entity_id', $data['seller_entity_id'])
            ->where('buyer_entity_id', $data['buyer_entity_id'])
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();

        if ($duplikat) {
            $this->addError('form.buyer_entity_id', 'Pasangan penjual–pembeli ini sudah dicatat untuk periode tersebut. Ubah baris yang ada.');

            return;
        }

        $nilai = [
            'period' => $data['period'],
            'seller_entity_id' => (int) $data['seller_entity_id'],
            'buyer_entity_id' => (int) $data['buyer_entity_id'],
            'planned_amount' => $data['planned_amount'] === '' ? null : (float) $data['planned_amount'],
            'actual_amount' => $data['actual_amount'] === '' ? null : (float) $data['actual_amount'],
            'notes' => trim((string) $data['notes']) ?: null,
        ];

        $this->editingId
            ? IntercompanySale::findOrFail($this->editingId)->update($nilai)
            : IntercompanySale::create($nilai);

        $this->showForm = false;
        $this->editingId = null;
        session()->flash('message', 'Penjualan antarentitas '.$nilai['period'].' tersimpan.');
    }

    public function delete(int $id): void
    {
        $this->ensureHoldingUser();
        if ($this->lacksPermission('manage consolidation')) {
            return;
        }

        IntercompanySale::whereKey($id)->delete();
        session()->flash('message', 'Baris eliminasi dihapus.');
    }

    /**
     * Buang ringkasan tersimpan lalu ambil ulang dari sumber tiap entitas
     * (database entitas atau API-nya).
     */
    public function refreshSummaries(): void
    {
        $this->ensureHoldingUser();

        // Satu penyegaran tiap 10 detik per pengguna: memanggil server entitas
        // berkali-kali tidak membuat angkanya lebih baru.
        $jeda = 'bsc:segarkan:'.(auth()->id() ?? 'x');

        if (Cache::get($jeda)) {
            session()->flash('error', 'Ringkasan baru saja diambil; coba lagi beberapa detik.');

            return;
        }

        Cache::put($jeda, true, 10);
        app(Consolidation::class)->refresh($this->period);

        session()->flash('message', 'Ringkasan entitas diambil ulang dari sumbernya.');
    }

    public function render()
    {
        $this->ensureHoldingUser();

        return view('livewire.holding-consolidation', [
            'data' => app(Consolidation::class)->forPeriod($this->period),
            'entities' => Entity::active()->get(),
            'canManage' => (bool) auth()->user()?->can('manage consolidation'),
        ])->layout('layouts.app', ['title' => 'Konsolidasi Holding']);
    }
}
