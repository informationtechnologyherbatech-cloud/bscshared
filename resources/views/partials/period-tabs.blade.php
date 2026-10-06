{{--
    Tab tahun pada pemilih periode di bilah atas.

    Berpindah tahun hanya mengganti kisi bulan yang tampil — tanpa memuat ulang
    halaman, karena seluruh tahun sudah dirender dan yang tidak aktif disembunyikan.

    Penyadapnya dipasang sekali di document (bukan pada tiap tombol) supaya tetap
    bekerja walau bagian navbar digambar ulang.
--}}
<script>
    if (! window.bscTabPeriode) {
        window.bscTabPeriode = true;

        document.addEventListener('click', function (e) {
            const tab = e.target.closest ? e.target.closest('[data-tab-tahun]') : null;

            if (! tab) {
                return;
            }

            // Tombol ini berada di dalam dropdown berisi form; jangan sampai ikut mengirim.
            e.preventDefault();
            e.stopPropagation();

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
        });
    }
</script>
