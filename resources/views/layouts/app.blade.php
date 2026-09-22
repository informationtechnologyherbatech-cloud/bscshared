<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? app_display_name() }} - {{ company_name() }}</title>

    <!-- Favicon entitas (dapat diganti di menu Setting Sistem) -->
    <link rel="icon" href="{{ entity_favicon() }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Source+Sans+Pro:wght@300;400;600;700&display=fallback" rel="stylesheet">

    <!-- Font Awesome Icons (Local with CDN fallback) -->
    @if(file_exists(public_path('vendor/fontawesome/css/all.min.css')))
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    @else
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @endif

    <!-- AdminLTE v3 Theme (Local with CDN fallback) -->
    @if(file_exists(public_path('vendor/adminlte/css/adminlte.min.css')))
        <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/adminlte.min.css') }}">
    @else
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    @endif

    <!-- Custom Super Apps BSC Styling -->
    <link rel="stylesheet" href="{{ asset('css/custom-app.css') }}">

    @livewireStyles
    @include('partials.input-rupiah-script')
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-dark navbar-teal">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link nb-toggle" data-widget="pushmenu" href="#" role="button" title="Buka/tutup menu"><i class="fas fa-bars-staggered"></i></a>
            </li>
            {{-- Nama aplikasi tidak diulang di sini: sudah tampil pada brand sidebar. --}}
        </ul>

        <!-- Right navbar links: tiap info tampil sebagai "chip" — ikon berbingkai + keterangan kecil + nilai. -->
        <ul class="navbar-nav ml-auto align-items-center">
            @auth
            @php($konteks = app(\App\Support\EntityContext::class))
            @php($entitasAktif = $konteks->entity())
            @php($logoAktif = $entitasAktif ? public_image_url(config('entity.profiles.'.$entitasAktif->code.'.logo')) : null)
            @if($entitasAktif)
            <li class="nav-item dropdown">
                @if($konteks->canSwitch(auth()->user()))
                    <a class="nav-link nb-chip" data-toggle="dropdown" href="#" title="Pilih entitas yang ditampilkan">
                        @if($logoAktif)<span class="nb-chip-icon nb-chip-logo"><img src="{{ $logoAktif }}" alt="{{ $entitasAktif->name }}"></span>@else<span class="nb-chip-icon"><i class="fas fa-building"></i></span>@endif
                        <span class="nb-chip-text">
                            <small>Entitas</small>
                            <strong>{{ $entitasAktif->name }}</strong>
                        </span>
                        <i class="fas fa-chevron-down nb-chip-caret"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right nb-entity-menu">
                        <div class="nb-entity-menu-head">
                            <i class="fas fa-arrow-right-arrow-left mr-2"></i>Tampilkan data entitas
                        </div>
                        @foreach($konteks->accessibleFor(auth()->user()) as $e)
                            @php($aktif = $e->id === $entitasAktif->id)
                            @php($logoEntitas = public_image_url(config('entity.profiles.'.$e->code.'.logo')))
                            <form method="POST" action="{{ route('entity.switch') }}" class="m-0">
                                @csrf
                                <input type="hidden" name="entity_id" value="{{ $e->id }}">
                                <button type="submit" class="dropdown-item nb-entity-item {{ $aktif ? 'is-active' : '' }}" @if($aktif) aria-current="true" @endif>
                                    <span class="nb-entity-logo">
                                        @if($logoEntitas)
                                            <img src="{{ $logoEntitas }}" alt="">
                                        @else
                                            {{ mb_substr($e->name, 0, 2) }}
                                        @endif
                                    </span>
                                    <span class="nb-entity-text">
                                        <strong>{{ $e->name }}</strong>
                                        <small>{{ $e->legal_name }}</small>
                                        <span class="nb-entity-tag">{{ $e->industryLabel() }}</span>
                                    </span>
                                    <i class="fas {{ $aktif ? 'fa-circle-check' : 'fa-chevron-right' }} nb-entity-mark"></i>
                                </button>
                            </form>
                        @endforeach
                    </div>
                @else
                    <span class="nav-link nb-chip" title="{{ $entitasAktif->legal_name }}">
                        @if($logoAktif)<span class="nb-chip-icon nb-chip-logo"><img src="{{ $logoAktif }}" alt="{{ $entitasAktif->name }}"></span>@else<span class="nb-chip-icon"><i class="fas fa-building"></i></span>@endif
                        <span class="nb-chip-text">
                            <small>Entitas</small>
                            <strong>{{ $entitasAktif->name }}</strong>
                        </span>
                    </span>
                @endif
            </li>
            @endif
            @endauth
            @auth
            {{-- Periode aktif: berlaku di semua halaman (disimpan di sesi, per entitas).
                 Bawaannya bulan berjalan; memilih periode di halaman juga mengubahnya. --}}
            @php($daftarPeriode = \App\Models\Period::orderByDesc('period')->get(['period', 'status']))
            @if($daftarPeriode->isNotEmpty())
            @php($periodeAktif = \App\Models\Period::active())
            @php($bulanIni = now()->format('Y-m'))
            @php($namaBulan = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'])
            <li class="nav-item dropdown d-none d-sm-block">
                <a class="nav-link nb-chip" data-toggle="dropdown" href="#" title="Periode yang ditampilkan di semua halaman">
                    <span class="nb-chip-icon"><i class="fas fa-calendar-days"></i></span>
                    <span class="nb-chip-text">
                        <small>Periode</small>
                        <strong data-periode-aktif>{{ $periodeAktif }}</strong>
                    </span>
                    <i class="fas fa-chevron-down nb-chip-caret"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right nb-entity-menu nb-period-menu">
                    <div class="nb-entity-menu-head">
                        <i class="fas fa-calendar-check mr-2"></i>Periode untuk semua halaman
                    </div>
                    <div class="nb-period-scroll">
                        @foreach($daftarPeriode->groupBy(fn ($p) => substr($p->period, 0, 4)) as $tahun => $periodeTahun)
                            <div class="nb-period-year">{{ $tahun }}</div>
                            <div class="nb-period-grid">
                                @foreach($periodeTahun->sortBy('period') as $p)
                                    @php($pilih = $p->period === $periodeAktif)
                                    <form method="POST" action="{{ route('period.switch') }}" class="m-0">
                                        @csrf
                                        <input type="hidden" name="period" value="{{ $p->period }}">
                                        <button type="submit" data-periode="{{ $p->period }}"
                                                class="nb-period-item {{ $pilih ? 'is-active' : '' }}"
                                                title="{{ $namaBulan[substr($p->period, 5, 2)] ?? '' }} {{ $tahun }}{{ $p->status === 'CLOSED' ? ' · ditutup' : '' }}">
                                            <strong>{{ mb_substr($namaBulan[substr($p->period, 5, 2)] ?? $p->period, 0, 3) }}</strong>
                                            <small>
                                                @if($p->status === 'CLOSED')<i class="fas fa-lock"></i>@endif
                                                {{ $p->period === $bulanIni ? 'bulan ini' : $p->period }}
                                            </small>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="nb-period-foot">
                        <i class="fas fa-circle-info mr-1"></i>
                        Revenue (F1) dan rasio (F2) dihitung kumulatif sejak Januari. Untuk melihat capaian setahun, pilih bulan terakhir tahun itu.
                    </div>
                </div>
            </li>
            @endif
            @endauth
            @auth
            <li class="nav-item">
                @php($peran = auth()->user()->getRoleNames()->first() ?? 'User')
                @php($dept = auth()->user()->dept_code)
                @php($inisial = collect(preg_split('/\s+/', trim(auth()->user()->name)))->filter()->take(2)->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode(''))
                <a id="navLogoutTrigger" class="nav-link nb-chip nb-user" href="javascript:void(0)" onclick="event.preventDefault(); if(window.jQuery){ jQuery('#logoutModal').modal('show'); } return false;" title="Klik untuk logout — {{ auth()->user()->name }} ({{ $peran }}{{ $dept ? ' · '.$dept : '' }})">
                    <span class="nb-avatar">{{ $inisial ?: 'U' }}</span>
                    {{-- Peran &amp; departemen: satu-satunya info yang dulu hanya ada di panel sidebar. --}}
                    <span class="nb-chip-text d-none d-md-flex">
                        <strong>{{ Str::limit(auth()->user()->name, 18) }}</strong>
                        <small>{{ $peran }}{{ $dept ? ' · '.$dept : '' }}</small>
                    </span>
                    <i class="fas fa-right-from-bracket nb-chip-caret d-none d-md-inline"></i>
                </a>
            </li>
            @endauth
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-teal elevation-4">
        <!-- Brand Logo -->
        <a href="{{ route('dashboard') }}" class="brand-link bg-teal" title="{{ company_name() }}">
            {{-- Brand sidebar = logo grup EMC; logo entitas aktif tampil di chip navbar. --}}
            @php($logoBrand = public_image_url(config('entity.holding.logo')) ?? entity_logo())
            @if($logoBrand)
                <img src="{{ $logoBrand }}" alt="{{ config('entity.holding.name') }}" class="brand-image elevation-3 bg-white p-1"
                     style="max-height:33px; width:auto; max-width:120px; object-fit:contain; border-radius:6px;">
            @else
                <i class="fas fa-chart-line brand-image img-circle elevation-3 p-2 bg-white text-teal"></i>
            @endif
            <span class="brand-text brand-text-custom">{{ app_display_name() }}</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            {{-- Panel pengguna tidak ditampilkan di sini: nama, peran, dan departemen sudah
                 tampil pada sudut kanan navbar, sehingga sidebar tidak mengulanginya. --}}

            <!-- Sidebar Menu — RBAC 13 Menu PRD Tabel 4 (hanya tampil sesuai permission) -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
                    {{-- Menu dikelompokkan mengikuti tingkat piramida BSC; di tiap kelompok
                         urutannya mengikuti alur kerja: atur → isi → lihat hasil. --}}
                    @php($menuHolding = auth()->user()?->can('view consolidation') && app(\App\Support\EntityContext::class)->canSwitch(auth()->user()))
                    @if(auth()->user()?->can('view dashboard') || auth()->user()?->can('view wiring') || $menuHolding)
                    <li class="nav-header">RINGKASAN KINERJA</li>
                    @endif
                    @can('view dashboard')
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-layer-group"></i>
                            <p>Piramida BSC</p>
                        </a>
                    </li>
                    @endcan
                    @can('view wiring')
                    <li class="nav-item">
                        <a href="{{ route('bsc-wiring') }}" class="nav-link {{ request()->routeIs('bsc-wiring') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-diagram-project"></i>
                            <p>Wiring / Peta Hubungan</p>
                        </a>
                    </li>
                    @endcan
                    @if($menuHolding)
                    <li class="nav-item">
                        <a href="{{ route('consolidation') }}" class="nav-link {{ request()->routeIs('consolidation') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-city"></i>
                            <p>Konsolidasi Holding</p>
                        </a>
                    </li>
                    @endif

                    @canany(['manage revenue','view dashboard'])
                    <li class="nav-header">TINGKAT 1 · REVENUE</li>
                    <li class="nav-item">
                        <a href="{{ route('revenue-planning') }}" class="nav-link {{ request()->routeIs('revenue-planning') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>Perencanaan Target</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('revenue') }}" class="nav-link {{ request()->routeIs('revenue') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-bullseye"></i>
                            <p>Target & Realisasi</p>
                        </a>
                    </li>
                    @endcanany

                    @can('view ratios')
                    <li class="nav-header">TINGKAT 2 · RASIO KEUANGAN</li>
                    <li class="nav-item">
                        <a href="{{ route('ratio-catalog') }}" class="nav-link {{ request()->routeIs('ratio-catalog') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-scale-balanced"></i>
                            <p>Katalog Rasio</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('account-balances') }}" class="nav-link {{ request()->routeIs('account-balances') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-file-invoice-dollar"></i>
                            <p>Pos Akun</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('financial-ratios') }}" class="nav-link {{ request()->routeIs('financial-ratios') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-percent"></i>
                            <p>Rasio Keuangan</p>
                        </a>
                    </li>
                    @endcan

                    @canany(['view objectives','manage ratios','view ratios'])
                    <li class="nav-header">TINGKAT 3 · KPI & SASARAN MUTU</li>
                    <li class="nav-item">
                        <a href="{{ route('account-post-map') }}" class="nav-link {{ request()->routeIs('account-post-map') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-table-cells"></i>
                            <p>Peta Pos Akun</p>
                        </a>
                    </li>
                    @endcanany
                    @canany(['view objectives','manage ratios'])
                    <li class="nav-item">
                        <a href="{{ route('kpi-cascades') }}" class="nav-link {{ request()->routeIs('kpi-cascades') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-sitemap"></i>
                            <p>Cascade KPI</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('indicator-tests') }}" class="nav-link {{ request()->routeIs('indicator-tests') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-vial-circle-check"></i>
                            <p>Uji Indikator</p>
                        </a>
                    </li>
                    @endcanany
                    @can('view objectives')
                    <li class="nav-item">
                        <a href="{{ route('department-objectives') }}" class="nav-link {{ request()->routeIs('department-objectives') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-crosshairs"></i>
                            <p>Objective Departemen</p>
                        </a>
                    </li>
                    @endcan

                    @can('view actionplans')
                    <li class="nav-header">TINGKAT 4 · PROGRAM KERJA</li>
                    <li class="nav-item">
                        <a href="{{ route('action-plans') }}" class="nav-link {{ request()->routeIs('action-plans') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-list-check"></i>
                            <p>Program Kerja (Action)</p>
                        </a>
                    </li>
                    @endcan

                    @canany(['view dampak','view coa','view sensitivity','view skenario'])
                    <li class="nav-header">SIMULASI & ANALISIS</li>
                    @endcanany
                    @can('view dampak')
                    <li class="nav-item">
                        <a href="{{ route('dampak') }}" class="nav-link {{ request()->routeIs('dampak') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-flask"></i>
                            <p>Uji Dampak / What-If</p>
                        </a>
                    </li>
                    @endcan
                    @can('view coa')
                    <li class="nav-item">
                        <a href="{{ route('coa') }}" class="nav-link {{ request()->routeIs('coa') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calculator"></i>
                            <p>Simulasi CoA</p>
                        </a>
                    </li>
                    @endcan
                    @can('view sensitivity')
                    <li class="nav-item">
                        <a href="{{ route('sensitivity') }}" class="nav-link {{ request()->routeIs('sensitivity') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-sliders"></i>
                            <p>Sensitivitas</p>
                        </a>
                    </li>
                    @endcan
                    @can('view skenario')
                    <li class="nav-item">
                        <a href="{{ route('skenario') }}" class="nav-link {{ request()->routeIs('skenario') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-code-branch"></i>
                            <p>Skenario</p>
                        </a>
                    </li>
                    @endcan

                    @can('view ibp')
                    <li class="nav-header">PERENCANAAN</li>
                    <li class="nav-item">
                        <a href="{{ route('ibp') }}" class="nav-link {{ request()->routeIs('ibp') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-handshake"></i>
                            <p>Konsensus IBP</p>
                        </a>
                    </li>
                    @endcan

                    @can('view dokumentasi')
                    <li class="nav-header">DOKUMENTASI</li>
                    <li class="nav-item">
                        <a href="{{ route('dokumentasi') }}" class="nav-link {{ request()->routeIs('dokumentasi') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-book-open"></i>
                            <p>Dokumentasi Metode</p>
                        </a>
                    </li>
                    @endcan

                    @canany(['view integration','view staging','view gateway'])
                    <li class="nav-header">INTEGRASI & AUDIT</li>
                    @endcanany
                    @can('view integration')
                    <li class="nav-item">
                        <a href="{{ route('system-integration') }}" class="nav-link {{ request()->routeIs('system-integration') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-plug"></i>
                            <p>Integrasi & Gateway</p>
                        </a>
                    </li>
                    @endcan
                    @can('view staging')
                    <li class="nav-item">
                        <a href="{{ route('staging-logs') }}" class="nav-link {{ request()->routeIs('staging-logs') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>Staging & Audit Log</p>
                        </a>
                    </li>
                    @endcan
                    @can('view gateway')
                        @cannot('view integration')
                        <li class="nav-item">
                            <a href="{{ route('system-integration') }}" class="nav-link {{ request()->routeIs('system-integration') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-plug"></i>
                                <p>Integrasi & Gateway</p>
                            </a>
                        </li>
                        @endcannot
                        @cannot('view staging')
                        <li class="nav-item">
                            <a href="{{ route('staging-logs') }}" class="nav-link {{ request()->routeIs('staging-logs') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-clipboard-list"></i>
                                <p>Staging & Audit Log</p>
                            </a>
                        </li>
                        @endcannot
                    @endcan

                    @canany(['manage users','manage settings','view systeminfo','manage apikey','can_manage_users','manage units'])
                    <li class="nav-header">ADMINISTRASI</li>
                    @endcanany
                    @can('manage units')
                    <li class="nav-item">
                        <a href="{{ route('work-units') }}" class="nav-link {{ request()->routeIs('work-units') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-building-user"></i>
                            <p>Unit Kerja</p>
                        </a>
                    </li>
                    @endcan
                    @can('manage users')
                    <li class="nav-item">
                        <a href="{{ route('manage-users') }}" class="nav-link {{ request()->routeIs('manage-users') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-users-gear"></i>
                            <p>Manage User</p>
                        </a>
                    </li>
                    @endcan
                    @canany(['manage settings','view systeminfo','manage apikey'])
                    <li class="nav-item">
                        <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-gear"></i>
                            <p>Setting</p>
                        </a>
                    </li>
                    @endcanany
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        {{ $slot }}
    </div>
    <!-- /.content-wrapper -->

    <!-- Main Footer -->
    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">
            <span class="badge badge-secondary">{{ app_version() }}</span>
        </div>
        <strong>{{ entity_copyright() }} · {{ app_display_name() }}.</strong> Hak cipta dilindungi.
        @if(entity('company_address') || entity('company_phone') || entity('company_email'))
            <div class="text-muted small">
                @if(entity('company_address')) {{ entity('company_address') }} @endif
                @if(entity('company_phone')) <span class="mx-1">·</span> <i class="fas fa-phone-alt mr-1"></i>{{ entity('company_phone') }} @endif
                @if(entity('company_email')) <span class="mx-1">·</span> <i class="fas fa-envelope mr-1"></i><a href="mailto:{{ entity('company_email') }}">{{ entity('company_email') }}</a> @endif
                @if(entity('company_website')) <span class="mx-1">·</span> <i class="fas fa-globe mr-1"></i><a href="{{ entity('company_website') }}" target="_blank" rel="noopener">{{ entity('company_website') }}</a> @endif
            </div>
        @endif
    </footer>
</div>
<!-- ./wrapper -->

<!-- Logout Confirmation Popup (FR-15: click account icon -> popup logout) -->
@auth
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content modal-confirm">
      <button type="button" class="modal-close modal-close--float" data-dismiss="modal" aria-label="Tutup"><i class="fas fa-xmark"></i></button>
      <div class="modal-body">
        <div class="modal-confirm-icon"><i class="fas fa-right-from-bracket"></i></div>
        <h5 id="logoutModalLabel">Keluar dari aplikasi?</h5>
        <p>Anda akan keluar dari sesi <strong>{{ auth()->user()->name }}</strong>. Sesi diakhiri dan Anda diarahkan ke halaman login.</p>
      </div>
      <div class="modal-ft">
        <button type="button" class="btn btn-ghost" data-dismiss="modal">Batal</button>
        <form method="POST" action="{{ route('logout') }}" class="mb-0">
            @csrf
            <button type="submit" class="btn btn-danger"><i class="fas fa-right-from-bracket mr-1"></i> Ya, Logout</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endauth

{{-- Periode dipilih dari dalam halaman → perbarui chip & tanda aktif di navbar. --}}
<script>
    window.addEventListener('periode-aktif', (e) => {
        const periode = e.detail?.period;
        if (!periode) return;
        document.querySelectorAll('[data-periode-aktif]').forEach((el) => { el.textContent = periode; });
        document.querySelectorAll('.nb-period-item').forEach((el) => el.classList.toggle('is-active', el.dataset.periode === periode));
    });
</script>
{{-- Modal Livewire (.modal-lw): Esc menutupnya lewat tombol ✕-nya; klik latar gelap hanya untuk modal konfirmasi. --}}
<script>
    (() => {
        const tutupModal = (modal) => modal && modal.querySelector('.modal-close')?.click();
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape' || document.querySelector('.ss-panel')) return;
            const semua = document.querySelectorAll('.modal-lw');
            tutupModal(semua[semua.length - 1]);
        });
        document.addEventListener('mousedown', (e) => {
            // Hanya modal konfirmasi: formulir panjang tidak hilang karena salah klik.
            if (e.target.classList?.contains('modal-lw') && e.target.querySelector('.modal-confirm')) tutupModal(e.target);
        });
    })();
</script>

<!-- REQUIRED SCRIPTS (Local with CDN fallback) -->
@if(file_exists(public_path('vendor/jquery/jquery.min.js')))
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
@else
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
@endif

@if(file_exists(public_path('vendor/bootstrap/js/bootstrap.bundle.min.js')))
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
@else
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
@endif

@if(file_exists(public_path('vendor/adminlte/js/adminlte.min.js')))
    <script src="{{ asset('vendor/adminlte/js/adminlte.min.js') }}"></script>
@else
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
@endif

@livewireScripts
@include('partials.livewire-feedback')
@include('partials.smart-select')
<script>
// FR-15 fallback: pastikan klik profil selalu buka #logoutModal meski data-toggle terhalang Livewire/AdminLTE (fix # -> /# )
document.addEventListener('DOMContentLoaded', function(){
  var trigger = document.getElementById('navLogoutTrigger');
  var modal = document.getElementById('logoutModal');
  if(!trigger || !modal) return;
  trigger.addEventListener('click', function(e){
    e.preventDefault();
    if(window.jQuery && jQuery.fn.modal){ jQuery(modal).modal('show'); }
    else { modal.classList.add('show'); modal.style.display='block'; modal.setAttribute('aria-modal','true'); }
    return false;
  });
  // ESC & backdrop click close fallback
  modal.addEventListener('click', function(e){
    if(e.target === modal && window.jQuery){ jQuery(modal).modal('hide'); }
  });
});
</script>
</body>
</html>
