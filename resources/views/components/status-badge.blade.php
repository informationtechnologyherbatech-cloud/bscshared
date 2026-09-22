{{--
    Label status capaian yang seragam di semua tabel (rasio, sasaran mutu, telusur
    piramida). Skala sama dengan piramida: Tercapai ≥ 100% · Waspada 80–99% ·
    Di Bawah Target < 80%. Nilai lama "Off-Target" dibaca sebagai Di Bawah Target.
--}}
@props(['status'])
@php
    $label = match ($status) {
        'Tercapai' => ['badge-tercapai', 'fa-check-circle', 'Tercapai', 'Capaian ≥ 100% target'],
        'Waspada' => ['badge-waspada', 'fa-exclamation-circle', 'Waspada', 'Capaian 80–99% target'],
        \App\Support\Bsc\RatioEngine::TANPA_TARGET => ['badge-info', 'fa-question-circle', 'Belum Ada Target', 'Nilai sudah ada, target belum diisi'],
        'Belum Lengkap' => ['badge-secondary', 'fa-hourglass-half', 'Belum Lengkap', 'Data belum lengkap'],
        default => ['badge-dibawah', 'fa-times-circle', 'Di Bawah Target', 'Capaian < 80% target'],
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge '.$label[0].' px-2 py-1']) }} title="{{ $label[3] }}"><i class="fas {{ $label[1] }}"></i> {{ $label[2] }}</span>
