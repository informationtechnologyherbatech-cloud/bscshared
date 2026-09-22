{{--
    Isian nilai uang: tampil "Rp 1.000.000.000" saat diketik, tetapi yang dikirim
    ke Livewire tetap angka murni ("1000000000", desimal memakai titik).

    Pemakaian — wire:model (beserta modifiernya) dipasang seperti input biasa:
        <x-input-rupiah wire:model.live.debounce.500ms="rows.01.target" class="form-control form-control-sm text-right" />
    Opsi: prefix (bawaan "Rp"; "" untuk tanpa awalan), decimals (bawaan 2).
--}}
@props(['prefix' => 'Rp', 'decimals' => 2])
@php
    $model = $attributes->whereStartsWith('wire:model');
    $input = $attributes->whereDoesntStartWith('wire:model');
@endphp
<div x-data="inputRupiah(@js($prefix), {{ (int) $decimals }})" x-modelable="nilai" {{ $model }}>
    <input type="text" inputmode="decimal" autocomplete="off"
           :value="teks" x-on:input="ketik($event)" x-on:blur="rapikan()"
           {{ $input->has('class') ? $input : $input->merge(['class' => 'form-control']) }}>
</div>
