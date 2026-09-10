<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#003535">
    <title>{{ $title ?? 'Login' }} - {{ company_name() }}</title>
    <link rel="icon" href="{{ entity_favicon() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
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

    @livewireStyles
    <style>
        :root{
            --c-primary:#003535; --c-on-primary:#ffffff; --c-primary-container:#0e4d4d; --c-on-primary-container:#85bdbc; --c-inverse-primary:#99d1d0;
            --c-secondary:#006a6a; --c-on-secondary:#ffffff; --c-secondary-container:#75f6f6;
            --c-surface-lowest:#ffffff; --c-surface-container:#e7eeff; --c-surface-variant:#d8e3fa;
            --c-on-surface:#111c2c; --c-on-variant:#404848; --c-outline:#707978; --c-outline-variant:#bfc8c8; --c-error:#ba1a1a; --c-error-container:#ffdad6;
            --r:0.25rem; --r-lg:0.5rem; --r-xl:0.75rem; --r-full:9999px;
            --s-md:16px; --s-lg:24px; --s-xl:40px;
        }
        *{ font-family:'Hanken Grotesk',system-ui,sans-serif; }
        h1,h2,h3,h4{ font-family:'Manrope',sans-serif; }
        html,body{ height:100%; }
        body.login-page{
            min-height:100vh; margin:0; background:var(--c-surface-lowest); overflow-x:hidden;
        }
        /* Fullscreen split — no centered card, 100vw x 100vh */
        .login-shell{
            min-height:100vh; display:flex; width:100vw; height:100vh; padding:0; margin:0;
        }
        .login-full{
            display:flex; width:100%; height:100vh; overflow:hidden;
        }
        /* Brand zone 42% — Level 0 deep teal + dot-matrix tactile (DESIGN Elevation & Brand) */
        .login-brand{
            flex:0 0 42%; max-width:42%;
            background:
                radial-gradient(circle at 1px 1px, rgba(255,255,255,.11) 1px, transparent 0) 0 0 / 18px 18px,
                linear-gradient(165deg, var(--c-primary) 0%, var(--c-primary-container) 56%, #0f5e5e 100%);
            color:var(--c-on-primary);
            padding:var(--s-xl);
            position:relative; display:flex; flex-direction:column; justify-content:center;
            border-right:1px solid rgba(255,255,255,.08);
        }
        .login-brand::after{ content:""; position:absolute; inset:0; background:linear-gradient(180deg, transparent 48%, rgba(0,0,0,.14) 100%); pointer-events:none; }
        .login-brand > *{ position:relative; z-index:1; }
        .login-brand h3{ font-size:32px; font-weight:700; line-height:1.25; letter-spacing:-0.01em; color:var(--c-on-primary); } /* headline-xl 32 */
        .brand-subtitle{ font-size:14px; line-height:1.5; color:var(--c-on-primary-container); }
        .icon-circle{
            width:72px; height:72px; border-radius:var(--r-full); background:var(--c-surface-lowest); color:var(--c-primary);
            display:inline-flex; align-items:center; justify-content:center; font-size:28px;
            box-shadow:0 10px 28px rgba(0,32,32,.22); border:1px solid rgba(255,255,255,.7);
        }
        .brand-quote{ background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.14); border-radius:var(--r-lg); padding:16px 18px; }
        .brand-quote p{ font-size:16px; line-height:1.5; color:rgba(255,255,255,.92); margin:0; } /* body-md 16 */

        /* Action zone 58% — Level 1 white, 40px premium padding (DESIGN Layout) */
        .login-form-wrap{
            flex:0 0 58%; max-width:58%;
            background:var(--c-surface-lowest);
            padding:var(--s-xl) 48px;
            display:flex; flex-direction:column; justify-content:center;
            overflow-y:auto;
        }
        .login-form-wrap h4{ font-size:20px; font-weight:600; line-height:1.4; color:var(--c-on-surface); }
        .form-label-premium{ font-size:12px; font-weight:600; letter-spacing:.02em; line-height:1; color:var(--c-on-surface); display:block; margin-bottom:6px; }
        .input-group{ border-radius:var(--r); }
        .login-form-wrap .form-control{
            height:44px; border-radius:var(--r); border:1px solid var(--c-outline-variant); background:#F8FAFA;
            font-size:14px; color:var(--c-on-surface); box-shadow:none; transition:border-color .18s, box-shadow .18s, background .18s;
        }
        .login-form-wrap .form-control:focus{ border-color:var(--c-secondary); background:var(--c-surface-lowest); box-shadow:0 0 0 3px rgba(0,106,106,.14); }
        .login-form-wrap .input-group-text{
            border-radius:var(--r) 0 0 var(--r); background:#F8FAFA; border:1px solid var(--c-outline-variant); border-right:0; color:var(--c-outline); min-width:42px; justify-content:center;
        }
        .login-form-wrap .input-group .form-control{ border-radius:0 var(--r) var(--r) 0; border-left:0; }
        .login-form-wrap .input-group:focus-within .input-group-text{ border-color:var(--c-secondary); background:var(--c-surface-lowest); color:var(--c-secondary); }
        .btn-teal{
            height:44px; border-radius:var(--r); border:0; color:var(--c-on-secondary);
            background:linear-gradient(90deg, var(--c-secondary) 0%, #1CB5B5 100%);
            font-size:14px; font-weight:600; letter-spacing:.02em;
            box-shadow:0 6px 16px rgba(0,106,106,.22); transition:transform .14s, box-shadow .14s;
        }
        .btn-teal:hover{ transform:translateY(-1px); box-shadow:0 10px 22px rgba(0,106,106,.26); color:#fff; }
        .btn-teal:active{ transform:none; box-shadow:inset 0 1px 4px rgba(0,0,0,.14); }
        .alert{ border-radius:var(--r); font-size:14px; border:1px solid transparent; }
        .alert-success{ background:#e6f4f3; border-color:var(--c-inverse-primary); color:var(--c-primary-container); }
        .alert-danger{ background:var(--c-error-container); border-color:var(--c-error); color:#93000a; }
        .alert-warning{ background:var(--c-surface-container); border-color:var(--c-surface-variant); color:var(--c-on-variant); }
        .divider-hairline{ height:1px; background:var(--c-outline-variant); opacity:.35; }

        /* Mobile: stack vertical, full screen scroll */
        @media(max-width:767.98px){
            .login-shell{ height:auto; min-height:100vh; }
            .login-full{ flex-direction:column; height:auto; min-height:100vh; }
            .login-brand, .login-form-wrap{ flex-basis:auto; max-width:100%; width:100%; }
            .login-brand{ padding:var(--s-lg) var(--s-md); border-right:0; border-bottom:1px solid rgba(255,255,255,.08); min-height:42vh; }
            .login-form-wrap{ padding:var(--s-lg) var(--s-md); }
            .login-brand h3{ font-size:24px; }
        }
    </style>
</head>
<body class="hold-transition login-page">
{{ $slot }}
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
@livewireScripts
</body>
</html>
