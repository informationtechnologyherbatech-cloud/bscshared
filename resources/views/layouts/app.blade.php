<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Super Apps BSC' }}</title>

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
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-dark navbar-teal">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="{{ route('dashboard') }}" class="nav-link active">Super Apps BSC Hop 4</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto align-items-center">
            @php
                $activePeriod = request('period') ?? session('active_period') ?? ($globalPeriods[0] ?? '2026-08');
            @endphp
            <li class="nav-item dropdown mr-2">
                <a class="nav-link dropdown-toggle font-weight-bold" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-calendar-alt mr-1"></i> Periode: <span class="badge badge-light text-teal px-2 py-1 ml-1">{{ $activePeriod }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow border-0" style="min-width: 180px;">
                    <span class="dropdown-header font-weight-bold text-muted small"><i class="fas fa-clock mr-1"></i> Pilih Periode Aktif:</span>
                    <div class="dropdown-divider my-1"></div>
                    @if(isset($globalPeriods) && count($globalPeriods) > 0)
                        @foreach($globalPeriods as $gp)
                            <a class="dropdown-item d-flex justify-content-between align-items-center {{ $activePeriod === $gp ? 'active bg-teal font-weight-bold' : '' }}" 
                               href="{{ request()->fullUrlWithQuery(['period' => $gp]) }}">
                                <span><i class="fas fa-calendar-day mr-2"></i> {{ $gp }}</span>
                                @if($activePeriod === $gp)
                                    <i class="fas fa-check small"></i>
                                @endif
                            </a>
                        @endforeach
                    @else
                        <a class="dropdown-item disabled" href="#">2026-08</a>
                    @endif
                </div>
            </li>
            @auth
            <li class="nav-item">
                <a id="navLogoutTrigger" class="nav-link d-flex align-items-center" href="javascript:void(0)" onclick="event.preventDefault(); if(window.jQuery){ jQuery('#logoutModal').modal('show'); } return false;" title="Klik untuk logout — {{ auth()->user()->name }} ({{ auth()->user()->getRoleNames()->first() ?? 'User' }})" style="cursor:pointer;">
                    <i class="fas fa-user-circle mr-1"></i>
                    <span class="d-none d-md-inline">{{ Str::limit(auth()->user()->name, 18) }}</span>
                    <small class="badge badge-light ml-2 d-none d-lg-inline">{{ auth()->user()->getRoleNames()->first() ?? 'User' }}</small>
                </a>
            </li>
            @endauth
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-teal elevation-4">
        <!-- Brand Logo -->
        <a href="{{ route('dashboard') }}" class="brand-link bg-teal">
            <i class="fas fa-chart-line brand-image img-circle elevation-3 p-2 bg-white text-teal"></i>
            <span class="brand-text brand-text-custom">SuperApps BSC</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <div class="img-circle elevation-2 bg-info text-center text-white" style="width: 34px; height: 34px; line-height: 34px; font-weight: bold;">
                        {{ auth()->check() ? strtoupper(substr(auth()->user()->name,0,2)) : 'SA' }}
                    </div>
                </div>
                <div class="info">
                    <a href="#" class="d-block">{{ auth()->check() ? Str::limit(auth()->user()->name,20) : 'Administrator BSC' }}</a>
                    @auth
                    <small class="text-white-50 d-block" style="font-size:11px; opacity:.7;">{{ auth()->user()->getRoleNames()->first() ?? '-' }} {{ auth()->user()->dept_code ? '· '.auth()->user()->dept_code : '' }}</small>
                    @endauth
                </div>
            </div>

            <!-- Sidebar Menu — RBAC 13 Menu PRD Tabel 4 (hanya tampil sesuai permission) -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-header">KONSOLIDASI KINERJA</li>
                    @can('view dashboard')
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Piramida BSC</p>
                        </a>
                    </li>
                    <li class="nav-header">LEVEL 1</li>
                    <li class="nav-item">
                        <a href="{{ route('apex-consolidation', ['tab' => 'input']) }}" class="nav-link {{ request()->routeIs('apex-consolidation') && (request('tab') === 'input' || !request('tab')) ? 'active' : '' }}">
                            <i class="nav-icon fas fa-edit text-teal"></i>
                            <p>Input & Bobot Revenue</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('apex-consolidation', ['tab' => 'dupont']) }}" class="nav-link {{ request()->routeIs('apex-consolidation') && request('tab') === 'dupont' ? 'active' : '' }}">
                            <i class="nav-icon fas fa-sitemap text-info"></i>
                            <p>DuPont Value Tree</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('apex-consolidation', ['tab' => 'sensitivity']) }}" class="nav-link {{ request()->routeIs('apex-consolidation') && request('tab') === 'sensitivity' ? 'active' : '' }}">
                            <i class="nav-icon fas fa-sliders-h text-warning"></i>
                            <p>Simulator Sensitivitas</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('apex-consolidation', ['tab' => 'weights']) }}" class="nav-link {{ request()->routeIs('apex-consolidation') && request('tab') === 'weights' ? 'active' : '' }}">
                            <i class="nav-icon fas fa-layer-group text-primary"></i>
                            <p>Matriks Kontribusi 5 Pilar</p>
                        </a>
                    </li>
                    @endcan
                    @can('view ratios')
                    <li class="nav-header">LEVEL 2</li>
                    <li class="nav-item">
                        <a href="{{ route('financial-ratios') }}" class="nav-link {{ request()->routeIs('financial-ratios') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-coins text-success"></i>
                            <p>Rasio Keuangan</p>
                        </a>
                    </li>
                    @endcan
                    @can('view objectives')
                    <li class="nav-header">LEVEL 3</li>
                    <li class="nav-item">
                        <a href="{{ route('department-objectives') }}" class="nav-link {{ request()->routeIs('department-objectives') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-bullseye text-warning"></i>
                            <p>Objective Departemen</p>
                        </a>
                    </li>
                    @endcan
                    @can('view actionplans')
                    <li class="nav-header">LEVEL 4</li>
                    <li class="nav-item">
                        <a href="{{ route('action-plans') }}" class="nav-link {{ request()->routeIs('action-plans') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tasks text-info"></i>
                            <p>Program Kerja (Action)</p>
                        </a>
                    </li>
                    @endcan
                    @can('view wiring')
                    <li class="nav-item">
                        <a href="{{ route('bsc-wiring') }}" class="nav-link {{ request()->routeIs('bsc-wiring') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-project-diagram text-teal"></i>
                            <p>Wiring / Peta Hubungan</p>
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
                    <li class="nav-item">
                        <a href="{{ route('peta-pos-akun') }}" class="nav-link {{ request()->routeIs('peta-pos-akun') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-sitemap text-teal"></i>
                            <p>Peta Pos Akun</p>
                        </a>
                    </li>
                    @endcan
                    @can('view sensitivity')
                    <li class="nav-item">
                        <a href="{{ route('sensitivity') }}" class="nav-link {{ request()->routeIs('sensitivity') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-area"></i>
                            <p>Sensitivitas</p>
                        </a>
                    </li>
                    @endcan
                    @can('view skenario')
                    <li class="nav-item">
                        <a href="{{ route('skenario') }}" class="nav-link {{ request()->routeIs('skenario') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-layer-group"></i>
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
                            <i class="nav-icon fas fa-book"></i>
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
                            <i class="nav-icon fas fa-network-wired"></i>
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
                                <i class="nav-icon fas fa-network-wired"></i>
                                <p>Staging & Audit Log</p>
                            </a>
                        </li>
                        @endcannot
                    @endcan

                    @canany(['manage users','manage settings','view systeminfo','manage apikey','can_manage_users'])
                    <li class="nav-header">ADMINISTRASI</li>
                    <li class="nav-item">
                        <a href="{{ route('master-departments') }}" class="nav-link {{ request()->routeIs('master-departments') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-building text-teal"></i>
                            <p>Master Departemen</p>
                        </a>
                    </li>
                    @endcanany
                    @can('manage users')
                    <li class="nav-item">
                        <a href="{{ route('manage-users') }}" class="nav-link {{ request()->routeIs('manage-users') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-users-cog"></i>
                            <p>Manage User</p>
                        </a>
                    </li>
                    @endcan
                    @canany(['manage settings','view systeminfo','manage apikey'])
                    <li class="nav-item">
                        <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-cogs"></i>
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
            Version 1.0 (Basic Livewire)
        </div>
        <strong>PT. Abhimata Emas Juara &copy; 2026 <a href="#">Super Apps BSC Hop 4</a>.</strong> All rights reserved.
    </footer>
</div>
<!-- ./wrapper -->

<!-- Logout Confirmation Popup (FR-15: click account icon -> popup logout) -->
@auth
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow-lg" style="border-radius:14px; overflow:hidden;">
      <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title font-weight-bold" id="logoutModalLabel"><i class="fas fa-sign-out-alt mr-2"></i> Konfirmasi Logout</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="mx-auto bg-light rounded-circle d-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
            <i class="fas fa-user-times text-danger" style="font-size:28px;"></i>
        </div>
        <p class="mb-1">Anda akan keluar dari sesi <strong>{{ auth()->user()->name }}</strong></p>
        <p class="text-muted small mb-0">Sesi akan diakhiri dan Anda akan diarahkan ke halaman login. Lanjutkan?</p>
      </div>
      <div class="modal-footer bg-light border-0 d-flex justify-content-between">
        <button type="button" class="btn btn-secondary px-4" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Batal</button>
        <form method="POST" action="{{ route('logout') }}" class="mb-0">
            @csrf
            <button type="submit" class="btn btn-danger px-4 font-weight-bold"><i class="fas fa-sign-out-alt mr-1"></i> Ya, Logout</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endauth

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
