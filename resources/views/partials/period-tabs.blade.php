{{--
    Tab tahun pada pemilih periode di bilah atas.

    Berpindah tahun hanya mengganti kisi bulan yang tampil — tanpa memuat ulang
    halaman, karena seluruh tahun sudah dirender dan yang tidak aktif disembunyikan.

    Penyadapnya dipasang sekali di document (bukan pada tiap tombol) supaya tetap
    bekerja walau bagian navbar digambar ulang.

    Fase CAPTURE, bukan bubble: Bootstrap memasang penyadapnya sendiri di document
    lebih dulu (berkasnya dimuat sebelum partial ini), dan penyadap itu menutup
    dropdown pada setiap klik. Di fase bubble, dropdown sudah menutup sebelum tab
    sempat berpindah — terlihat seperti tombolnya tidak berfungsi.
--}}
<script>
    if (! window.bscTabPeriode) {
        window.bscTabPeriode = true;

        document.addEventListener('click', function (e) {
            const tab = e.target.closest ? e.target.closest('[data-tab-tahun]') : null;

            if (! tab) {
                return;
            }

            // Tombol ini berada di dalam dropdown berisi form; jangan sampai ikut
            // mengirim, dan jangan sampai Bootstrap menutup dropdownnya.
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            const menu = tab.closest('.nb-period-menu');

            if (! menu) {
                return;
            }

            const tahun = tab.dataset.tabTahun;

            menu.querySelectorAll('[data-tab-tahun]').forEach(function (t) {
                const aktif = t.dataset.tabTahun === tahun;
                t.classList.toggle('is-active', aktif);
                t.setAttribute('aria-selected', aktif ? 'true' : 'false');
            });

            menu.querySelectorAll('[data-panel-tahun]').forEach(function (panel) {
                panel.hidden = panel.dataset.panelTahun !== tahun;
            });
        }, true);

        /*
         * Tahun aktif digeser ke dalam pandangan saat dropdown dibuka. Tanpa ini,
         * pengguna yang sedang berada di tahun lama membuka dropdown dan hanya
         * melihat tahun-tahun terbaru — tahunnya sendiri tersembunyi di kanan.
         */
        const tampilkanTahunAktif = function () {
            document.querySelectorAll('.nb-period-tabs').forEach(function (baris) {
                const aktif = baris.querySelector('.nb-period-tab.is-active');

                if (aktif) {
                    // Hitung sendiri, bukan scrollIntoView(): scrollIntoView juga
                    // menggulung halaman di belakang dropdown.
                    baris.scrollLeft = aktif.offsetLeft - (baris.clientWidth - aktif.offsetWidth) / 2;
                }
            });
        };

        // "shown.bs.dropdown" adalah event jQuery milik Bootstrap 4 — tidak
        // terdengar oleh addEventListener biasa. Pakai jQuery bila ada, dan
        // sediakan cadangan berbasis klik supaya tetap bekerja tanpa jQuery.
        if (window.jQuery) {
            window.jQuery(document).on('shown.bs.dropdown', tampilkanTahunAktif);
        } else {
            document.addEventListener('click', function (e) {
                if (e.target.closest && e.target.closest('[data-toggle="dropdown"]')) {
                    setTimeout(tampilkanTahunAktif, 60);
                }
            });
        }
    }
</script>
