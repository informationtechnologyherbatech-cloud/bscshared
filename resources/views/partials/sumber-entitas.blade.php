{{--
    Pemberitahuan pada pemasangan HOLDING ketika entitas yang sedang dipilih
    datanya berada di sumber lain (database entitas atau server entitas).

    Halaman tingkat entitas (Piramida, Rasio, Objective, …) membaca database
    aplikasi ini, sedangkan datanya ada di entitas — tanpa keterangan ini
    halamannya terlihat "kosong tanpa sebab". Angka entitas tersedia di
    Konsolidasi Holding, yang memang mengambilnya dari sumbernya.
--}}
@auth
    @if (entity_source_is_remote() && ! request()->routeIs('consolidation'))
        <div class="px-3 pt-3">
            <div class="alert alert-info mb-0 py-2">
                <i class="fas fa-database mr-1"></i>
                Data <strong>{{ entity_name() }}</strong> disimpan di sumbernya ({{ entity_source_label() }}), bukan di database aplikasi holding ini.
                Angka ringkasnya ada di
                <a class="font-weight-bold" href="{{ route('consolidation') }}">Konsolidasi Holding</a>;
                untuk mengisi atau menelusuri datanya, buka aplikasi entitas tersebut.
            </div>
        </div>
    @endif
@endauth
