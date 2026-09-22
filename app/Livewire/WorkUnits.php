<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\AccountPostRole;
use App\Models\DepartmentObjective;
use App\Models\WorkUnit;
use App\Support\EntityContext;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Master unit kerja (departemen) milik entitas yang sedang aktif.
 *
 * Sebelumnya daftar departemen hanya diturunkan dari DISTINCT dept_code pada
 * sasaran mutu, sehingga tidak dapat ditambah, diubah, maupun disesuaikan
 * dengan struktur organisasi tiap entitas.
 */
class WorkUnits extends Component
{
    use AuthorizesWrites;

    public string $search = '';

    public bool $showInactive = false;

    // Formulir
    public ?int $unitId = null;

    public string $code = '';

    public string $name = '';

    public string $stream = '';

    public string $reports_to = '';

    public string $scope = '';

    public bool $is_active = true;

    public int $sort = 0;

    public bool $showModal = false;

    public bool $codeLocked = false;

    public ?int $confirmDeleteId = null;

    protected function rules(): array
    {
        $entityId = app(EntityContext::class)->id();

        return [
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('work_units', 'code')
                    ->where('entity_id', $entityId)
                    ->ignore($this->unitId),
            ],
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'stream' => ['nullable', 'string', 'max:100'],
            'reports_to' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'sort' => ['integer', 'min:0', 'max:9999'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'code' => 'kode unit',
            'name' => 'nama unit',
            'stream' => 'stream',
            'reports_to' => 'lapor ke',
            'scope' => 'lingkup',
            'sort' => 'urutan',
        ];
    }

    protected function messages(): array
    {
        return [
            'code.regex' => 'Kode unit hanya boleh huruf besar, angka, garis bawah, atau tanda hubung (mis. SCM, TTC, QA-2).',
            'code.unique' => 'Kode unit ini sudah dipakai di entitas ini.',
        ];
    }

    /** Kode selalu disimpan huruf besar. */
    public function updatedCode(string $value): void
    {
        $this->code = strtoupper(trim($value));
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->sort = (int) WorkUnit::max('sort') + 1;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $unit = WorkUnit::findOrFail($id);

        $this->resetForm();
        $this->unitId = $unit->id;
        $this->code = $unit->code;
        $this->name = $unit->name;
        $this->stream = (string) $unit->stream;
        $this->reports_to = (string) $unit->reports_to;
        $this->scope = (string) $unit->scope;
        $this->is_active = $unit->is_active;
        $this->sort = $unit->sort;

        // Kode adalah penghubung ke sasaran mutu & program kerja — mengubahnya
        // akan membuat data itu kehilangan unitnya.
        $this->codeLocked = $unit->isInUse();
        $this->showModal = true;
    }

    public function save(): void
    {
        if ($this->lacksPermission('manage units')) {
            return;
        }

        $this->code = strtoupper(trim($this->code));
        $data = $this->validate();

        if ($this->unitId) {
            $unit = WorkUnit::findOrFail($this->unitId);

            if ($unit->code !== $data['code'] && $unit->isInUse()) {
                $this->addError('code', 'Kode tidak dapat diubah karena sudah dipakai sasaran mutu, program kerja, atau cascade KPI. Ubah namanya saja.');

                return;
            }

            $kodeLama = $unit->code;
            $unit->update($data);

            // Peran unit di Peta Pos Akun ikut berganti kode.
            if ($kodeLama !== $unit->code) {
                AccountPostRole::where('unit_code', $kodeLama)->update(['unit_code' => $unit->code]);
            }
            session()->flash('message', 'Unit kerja '.$unit->code.' berhasil diperbarui.');
        } else {
            $unit = WorkUnit::create($data);
            session()->flash('message', 'Unit kerja '.$unit->code.' berhasil ditambahkan.');
        }

        $this->closeModal();
    }

    public function toggleActive(int $id): void
    {
        if ($this->lacksPermission('manage units')) {
            return;
        }

        $unit = WorkUnit::findOrFail($id);
        $unit->update(['is_active' => ! $unit->is_active]);

        session()->flash('message', 'Unit kerja '.$unit->code.($unit->is_active ? ' diaktifkan kembali.' : ' dinonaktifkan.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function delete(): void
    {
        if ($this->lacksPermission('manage units')) {
            return;
        }

        $unit = WorkUnit::findOrFail($this->confirmDeleteId);

        if ($unit->isInUse()) {
            session()->flash('error', 'Unit kerja '.$unit->code.' masih dipakai '.$unit->usageCount()
                .' sasaran mutu/program kerja/KPI. Nonaktifkan saja agar riwayatnya tetap utuh.');
            $this->confirmDeleteId = null;

            return;
        }

        $kode = $unit->code;
        AccountPostRole::where('unit_code', $kode)->delete();
        $unit->delete();
        $this->confirmDeleteId = null;

        session()->flash('message', 'Unit kerja '.$kode.' dihapus.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['unitId', 'code', 'name', 'stream', 'reports_to', 'scope', 'codeLocked']);
        $this->is_active = true;
        $this->sort = 0;
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = WorkUnit::query()->orderBy('sort')->orderBy('code');

        if (! $this->showInactive) {
            $query->where('is_active', true);
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('code', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%')
                    ->orWhere('stream', 'like', '%'.$this->search.'%');
            });
        }

        $units = $query->get();

        // Jumlah pemakaian dihitung sekali per kode, bukan satu kueri per baris.
        $pemakaian = DepartmentObjective::query()
            ->selectRaw('dept_code, count(*) as jumlah')
            ->groupBy('dept_code')
            ->pluck('jumlah', 'dept_code');

        $streams = WorkUnit::query()->whereNotNull('stream')->distinct()->orderBy('stream')->pluck('stream');

        return view('livewire.work-units', [
            'units' => $units,
            'pemakaian' => $pemakaian,
            'streams' => $streams,
            'entity' => app(EntityContext::class)->entity(),
            'totalAktif' => WorkUnit::where('is_active', true)->count(),
        ])->layout('layouts.app', ['title' => 'Unit Kerja']);
    }
}
