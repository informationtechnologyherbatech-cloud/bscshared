{{--
    Umpan balik aksi Livewire di semua halaman:
      1. Tombol yang sedang diproses (Livewire memberi atribut data-loading) menampilkan
         ikon berputar dan tidak dapat diklik ulang.
      2. Notifikasi sukses/galat yang baru muncul tetapi berada di luar layar (mis. di
         atas halaman saat pengguna menekan Simpan di bawah tabel) ditampilkan juga
         sebagai toast melayang, lalu hilang sendiri.
--}}
<style>
    button[data-loading], a[data-loading], .btn[data-loading] { pointer-events: none; opacity: .72; position: relative; }
    button[data-loading] > i.fas, button[data-loading] > i.far { visibility: hidden; }
    button[data-loading]::before {
        content: ""; position: absolute; left: .7em; top: 50%; width: .9em; height: .9em; margin-top: -.45em;
        border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%;
        animation: bsc-putar .6s linear infinite;
    }
    button[data-loading]:not(:has(> i))::before { position: static; display: inline-block; margin: 0 .4em -.1em 0; }
    @keyframes bsc-putar { to { transform: rotate(360deg); } }

    #bsc-toasts { position: fixed; right: 16px; bottom: 16px; z-index: 2000; display: flex; flex-direction: column; gap: 8px; max-width: min(420px, calc(100vw - 32px)); }
    .bsc-toast {
        display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 8px; color: #fff;
        box-shadow: 0 10px 28px rgba(0, 0, 0, .22); font-size: 14px; line-height: 1.45;
        animation: bsc-masuk .22s ease-out;
    }
    .bsc-toast--sukses { background: #1e7e34; }
    .bsc-toast--galat { background: #c82333; }
    .bsc-toast--info { background: #117a8b; }
    .bsc-toast i.fas { margin-top: 2px; }
    .bsc-toast button { margin-left: auto; background: none; border: 0; color: inherit; opacity: .8; font-size: 18px; line-height: 1; cursor: pointer; }
    .bsc-toast.keluar { opacity: 0; transform: translateY(8px); transition: all .25s ease-in; }
    @keyframes bsc-masuk { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) { .bsc-toast, button[data-loading]::before { animation: none; } }
</style>
<div id="bsc-toasts" role="status" aria-live="polite"></div>
<script>
    (function () {
        const sudah = new WeakSet();
        const terakhir = new Map(); // teks → waktu tampil, cegah toast ganda

        function toast(teks, jenis) {
            const kunci = jenis + '|' + teks;
            const kini = Date.now();
            if (terakhir.has(kunci) && kini - terakhir.get(kunci) < 4000) return;
            terakhir.set(kunci, kini);

            const ikon = { sukses: 'fa-check-circle', galat: 'fa-exclamation-triangle', info: 'fa-info-circle' }[jenis];
            const el = document.createElement('div');
            el.className = 'bsc-toast bsc-toast--' + jenis;
            el.innerHTML = '<i class="fas ' + ikon + '"></i><span></span><button type="button" aria-label="Tutup">&times;</button>';
            el.querySelector('span').textContent = teks;
            const tutup = () => { el.classList.add('keluar'); setTimeout(() => el.remove(), 260); };
            el.querySelector('button').addEventListener('click', tutup);
            document.getElementById('bsc-toasts').appendChild(el);
            setTimeout(tutup, jenis === 'galat' ? 7000 : 4500);
        }

        function diLuarLayar(el) {
            const r = el.getBoundingClientRect();
            const atas = 60; // tinggi navbar
            return r.bottom < atas || r.top > window.innerHeight;
        }

        function periksa(root) {
            root.querySelectorAll('.alert.alert-success, .alert.alert-danger, .alert.alert-warning').forEach((alert) => {
                if (sudah.has(alert)) return;
                sudah.add(alert);
                if (!diLuarLayar(alert)) return;
                const teks = alert.textContent.replace('×', '').replace(/\s+/g, ' ').trim();
                if (!teks) return;
                toast(teks, alert.classList.contains('alert-success') ? 'sukses' : (alert.classList.contains('alert-danger') ? 'galat' : 'info'));
            });
        }

        // Notifikasi yang sudah ada saat halaman dimuat dianggap sudah terlihat.
        document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.alert').forEach((a) => sudah.add(a)));

        const daftarkan = () => window.Livewire.hook('morphed', ({ el }) => requestAnimationFrame(() => periksa(el)));
        window.Livewire ? daftarkan() : document.addEventListener('livewire:init', daftarkan);

        window.bscToast = toast;
    })();
</script>
