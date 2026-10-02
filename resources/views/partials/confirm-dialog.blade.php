{{--
    Dialog konfirmasi aplikasi — pengganti kotak bawaan peramban.

    `wire:confirm` milik Livewire memanggil window.confirm(), yang tampil sebagai
    kotak abu-abu bertuliskan nama domain ("superapps.erdigma.co.id says") dan
    tidak dapat ditata sama sekali. Di sini tombol cukup diberi atribut
    data-konfirmasi, lalu klik pertamanya ditahan dan digantikan dialog yang
    seragam dengan modal lain di aplikasi ini.

        <button wire:click="hapus(7)"
                data-konfirmasi="Hapus baris ini? Tindakan ini tidak dapat dibatalkan."
                data-konfirmasi-judul="Hapus eliminasi"      <- opsional
                data-konfirmasi-ok="Hapus"                   <- opsional
                data-konfirmasi-nada="bahaya">               <- opsional: bahaya|utama

    Keterangan bertanda <- di atas hanya penjelasan, bukan bagian dari kodenya.
    Komentar Blade tidak boleh bersarang: penutup komentar yang pertama ditemukan
    mengakhiri seluruh komentar, sehingga sisanya ikut tercetak di halaman.

    Cara kerja: satu penyadap pada fase CAPTURE di document. Livewire memasang
    pendengarnya pada elemen tombol, jadi menghentikan perambatan di tahap capture
    membuat aksinya benar-benar tertunda — bukan berjalan lalu dibatalkan. Setelah
    pengguna menekan "Ya", tombol yang sama diklik ulang dengan penanda lolos,
    sehingga Livewire menjalankannya seperti biasa.
--}}
<style>
    .konfirmasi-dialog { max-width: 460px; }
    .konfirmasi-pesan {
        margin: 0;
        color: var(--c-text-primary, #21323c);
        font-size: .95rem;
        line-height: 1.55;
        white-space: pre-line;   /* pesan berbaris banyak tetap terbaca */
    }
    .modal-hd-icon.is-bahaya {
        color: #b02a37;
        background: #fdecee;
        box-shadow: inset 0 0 0 1px rgba(176, 42, 55, .14);
    }
</style>

<div id="konfirmasi-lapis" class="modal modal-lw modal-confirm" tabindex="-1" role="dialog"
     aria-modal="true" aria-labelledby="konfirmasi-judul" style="display:none">
    <div class="modal-dialog modal-dialog-centered konfirmasi-dialog">
        <div class="modal-content">
            <div class="modal-hd">
                <span class="modal-hd-icon" id="konfirmasi-ikon"><i class="fas fa-circle-question"></i></span>
                <div>
                    <h5 class="modal-title" id="konfirmasi-judul">Perlu dipastikan</h5>
                </div>
            </div>
            <div class="modal-body">
                <p class="konfirmasi-pesan" id="konfirmasi-pesan"></p>
            </div>
            <div class="modal-ft">
                <button type="button" class="btn btn-ghost btn-sm" id="konfirmasi-batal">Batal</button>
                <button type="button" class="btn btn-teal btn-sm" id="konfirmasi-ya">Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const lapis = document.getElementById('konfirmasi-lapis');

    if (!lapis) {
        return;
    }

    const judul = document.getElementById('konfirmasi-judul');
    const pesan = document.getElementById('konfirmasi-pesan');
    const ikon = document.getElementById('konfirmasi-ikon');
    const tombolYa = document.getElementById('konfirmasi-ya');
    const tombolBatal = document.getElementById('konfirmasi-batal');

    let sasaran = null;         // tombol yang sedang menunggu kepastian
    let fokusSebelumnya = null;

    function buka(el) {
        sasaran = el;
        fokusSebelumnya = document.activeElement;

        const bahaya = (el.dataset.konfirmasiNada || '') === 'bahaya';

        judul.textContent = el.dataset.konfirmasiJudul || (bahaya ? 'Hapus data ini?' : 'Perlu dipastikan');
        pesan.textContent = el.dataset.konfirmasi || 'Lanjutkan tindakan ini?';
        tombolYa.textContent = el.dataset.konfirmasiOk || (bahaya ? 'Ya, hapus' : 'Ya, lanjutkan');
        tombolYa.className = 'btn btn-sm ' + (bahaya ? 'btn-danger' : 'btn-teal');
        ikon.className = 'modal-hd-icon' + (bahaya ? ' is-bahaya' : '');
        ikon.innerHTML = '<i class="fas ' + (bahaya ? 'fa-triangle-exclamation' : 'fa-circle-question') + '"></i>';

        lapis.style.display = 'block';
        document.body.classList.add('modal-open');
        tombolYa.focus();
    }

    function tutup() {
        lapis.style.display = 'none';
        document.body.classList.remove('modal-open');
        sasaran = null;

        if (fokusSebelumnya && document.contains(fokusSebelumnya)) {
            fokusSebelumnya.focus();
        }
    }

    function lanjutkan() {
        const el = sasaran;
        tutup();

        if (!el) {
            return;
        }

        // Penanda sesaat: klik ulang berikutnya dibiarkan lewat.
        el.dataset.konfirmasiLolos = '1';
        el.click();
        delete el.dataset.konfirmasiLolos;
    }

    // Fase CAPTURE: berjalan sebelum pendengar milik Livewire pada tombolnya.
    document.addEventListener('click', function (e) {
        const el = e.target.closest ? e.target.closest('[data-konfirmasi]') : null;

        if (!el || el.dataset.konfirmasiLolos === '1' || el.disabled) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();
        buka(el);
    }, true);

    tombolYa.addEventListener('click', lanjutkan);
    tombolBatal.addEventListener('click', tutup);

    // Klik di luar kotak = batal, sama seperti modal lain.
    lapis.addEventListener('click', function (e) {
        if (e.target === lapis) {
            tutup();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (lapis.style.display === 'none') {
            return;
        }

        if (e.key === 'Escape') {
            e.preventDefault();
            tutup();
        }

        // Fokus ditahan di antara dua tombol selama dialog terbuka.
        if (e.key === 'Tab') {
            e.preventDefault();
            (document.activeElement === tombolYa ? tombolBatal : tombolYa).focus();
        }
    });

    // Livewire menggambar ulang halaman; dialog ini milik layout, jadi cukup
    // dipastikan tertutup agar tidak menggantung di atas tampilan baru.
    document.addEventListener('livewire:navigated', tutup);
})();
</script>
