{{--
    Ikon "?" pada judul kolom tabel rasio: menjelaskan dari mana isi kolom itu
    berasal — terutama Actual, yang dihitung aplikasi dan bukan diketik siapa pun.

    Sengaja memakai atribut `title` bawaan peramban, bukan tooltip berbasis JS:
    tabelnya berada di dalam .table-responsive yang memotong apa pun yang
    menonjol keluar, dan Livewire menggambar ulang tabel ini setiap saringan
    berubah — tooltip yang perlu dipasang ulang akan mati diam-diam.
--}}
@props(['for'])
@php($info = ratio_column_help($for))
<span {{ $attributes->merge(['class' => 'col-info']) }}
      tabindex="0" role="note"
      aria-label="{{ $info['judul'] }}: {{ $info['ringkas'] }}"
      title="{{ $info['judul'] }} — {{ $info['ringkas'] }}">
    <i class="fas fa-circle-question" aria-hidden="true"></i>
</span>
