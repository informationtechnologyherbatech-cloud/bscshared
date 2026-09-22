{{--
    Pilihan (select) yang bisa dicari, berlaku otomatis untuk semua <select> di aplikasi.

    Cara kerja: <select> asli tetap ada dan tetap menjadi sumber nilai (wire:model,
    x-model, dan form biasa tidak berubah). Klik/Enter/Spasi/panah bawah pada select
    tidak membuka daftar bawaan peramban, melainkan panel bergaya dengan kotak cari.
    Panel ditempel ke <body> sehingga tidak ikut diubah oleh morph Livewire. Memilih
    opsi menulis select.value lalu memicu event input + change, persis seperti
    pengguna memilih lewat daftar bawaan.

    Dikecualikan: select[multiple], select[size] > 1, dan select[data-native].
--}}
<style>
    .ss-panel {
        position: fixed; z-index: 2050; display: flex; flex-direction: column;
        min-width: 240px; max-width: min(560px, calc(100vw - 16px));
        background: #fff; border-radius: 12px; overflow: hidden;
        box-shadow: 0 14px 36px rgba(0, 34, 34, .22), 0 0 0 1px rgba(0, 53, 53, .08);
        animation: ss-masuk .14s ease-out;
    }
    .ss-panel[data-atas] { animation-name: ss-masuk-atas; }
    @keyframes ss-masuk { from { opacity: 0; transform: translateY(-4px); } }
    @keyframes ss-masuk-atas { from { opacity: 0; transform: translateY(4px); } }
    .ss-search { position: relative; padding: 8px; border-bottom: 1px solid #edf2f2; background: #fafcfc; }
    .ss-search i { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; }
    .ss-search input {
        width: 100%; height: 36px; padding: 0 10px 0 34px; border: 1px solid #cbd5e1; border-radius: 9px;
        font-size: 14px; outline: none; transition: border-color .12s, box-shadow .12s;
    }
    .ss-search input:focus { border-color: #1cb5b5; box-shadow: 0 0 0 3px rgba(28, 181, 181, .18); }
    .ss-list { overflow-y: auto; padding: 6px; overscroll-behavior: contain; }
    .ss-group { padding: 8px 10px 4px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
    .ss-opt {
        display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 8px;
        font-size: 14px; line-height: 1.35; color: #111c2c; cursor: pointer; user-select: none;
    }
    .ss-opt.is-active { background: #f1f5f9; }
    .ss-opt.is-selected { background: #e6f4f3; color: #004d4d; font-weight: 600; }
    .ss-opt.is-selected.is-active { background: #d5ecea; }
    .ss-opt.is-disabled { color: #a0aec0; cursor: not-allowed; }
    .ss-opt.is-placeholder .ss-text { color: #64748b; }
    .ss-code {
        flex: 0 0 auto; min-width: 42px; padding: 1px 7px; border-radius: 6px; text-align: center;
        font-size: 11.5px; font-weight: 700; letter-spacing: .02em; color: #006666; background: rgba(0, 128, 128, .1);
    }
    .ss-opt.is-selected .ss-code { color: #fff; background: #008080; }
    .ss-text { flex: 1; min-width: 0; }
    .ss-text small { display: block; color: #64748b; font-weight: 400; }
    .ss-check { margin-left: auto; color: #008080; font-size: 13px; visibility: hidden; }
    .ss-opt.is-selected .ss-check { visibility: visible; }
    .ss-opt mark { padding: 0; border-radius: 2px; color: inherit; background: #fde68a; }
    .ss-empty { padding: 18px 12px; text-align: center; color: #64748b; font-size: 13.5px; }
    .ss-foot { padding: 6px 12px; border-top: 1px solid #edf2f2; font-size: 11.5px; color: #94a3b8; background: #fafcfc; }
    select.ss-open { border-color: #1cb5b5 !important; box-shadow: 0 0 0 3px rgba(28, 181, 181, .18) !important; }
</style>
<script>
(() => {
    if (window.SmartSelect) return;

    const CARI_MIN = 6;          // kotak cari tampil bila opsi lebih dari ini
    const KODE = /^\s*([A-Z0-9][A-Z0-9._\/-]{1,11})\s+[—–-]\s+(.+)$/; // "SCM — Supply Chain"
    let aktif = null;            // { select, panel, input, list, items, index }

    const sentuh = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    const eligible = (s) => !sentuh && s instanceof HTMLSelectElement && !s.multiple && !(s.size > 1) && !s.hasAttribute('data-native');
    let urutId = 0;
    const norm = (t) => (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const esc = (t) => t.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function sorot(teks, kata) {
        if (!kata) return esc(teks);
        const i = norm(teks).indexOf(kata);
        if (i < 0) return esc(teks);
        return esc(teks.slice(0, i)) + '<mark>' + esc(teks.slice(i, i + kata.length)) + '</mark>' + esc(teks.slice(i + kata.length));
    }

    function bangunItem(select) {
        const items = [];
        for (const node of select.children) {
            if (node.tagName === 'OPTGROUP') {
                items.push({ group: node.label });
                for (const o of node.children) items.push({ option: o, label: node.label });
            } else if (node.tagName === 'OPTION') {
                items.push({ option: node });
            }
        }
        return items;
    }

    function render() {
        const { select, list, input } = aktif;
        const kata = norm(input ? input.value.trim() : '');
        list.innerHTML = '';
        aktif.visible = [];

        for (const it of aktif.items) {
            if (it.group !== undefined) continue;
            const o = it.option;
            if (o.hidden) continue;
            const teks = o.textContent.trim();
            if (kata && !norm(teks + ' ' + (it.label || '')).includes(kata)) continue;

            if (it.label && it.label !== aktif.grupTerakhir) {
                const g = document.createElement('div');
                g.className = 'ss-group';
                g.textContent = it.label;
                list.appendChild(g);
                aktif.grupTerakhir = it.label;
            }

            const el = document.createElement('div');
            el.className = 'ss-opt';
            el.setAttribute('role', 'option');
            el.id = aktif.panel.id + '-o' + aktif.visible.length;
            el.setAttribute('aria-selected', o.selected ? 'true' : 'false');
            if (o.disabled) el.setAttribute('aria-disabled', 'true');
            if (o.selected) el.classList.add('is-selected');
            if (o.disabled) el.classList.add('is-disabled');
            if (o.value === '') el.classList.add('is-placeholder');

            const m = teks.match(KODE);
            el.innerHTML = (m ? '<span class="ss-code">' + sorot(m[1], kata) + '</span><span class="ss-text">' + sorot(m[2], kata) + '</span>'
                              : '<span class="ss-text">' + sorot(teks || '\u00a0', kata) + '</span>')
                + '<i class="fas fa-check ss-check"></i>';
            el.addEventListener('mousedown', (e) => e.preventDefault());
            el.addEventListener('click', () => pilih(o));
            el.addEventListener('mousemove', () => tandai(aktif.visible.indexOf(el), false));
            el._option = o;
            list.appendChild(el);
            aktif.visible.push(el);
        }
        aktif.grupTerakhir = null;

        if (!aktif.visible.length) {
            list.innerHTML = '<div class="ss-empty"><i class="fas fa-magnifying-glass mr-1"></i> Tidak ada yang cocok dengan "' + esc(input.value.trim()) + '"</div>';
        }

        const terpilih = aktif.visible.findIndex((el) => el.classList.contains('is-selected'));
        tandai(terpilih >= 0 && !kata ? terpilih : aktif.visible.findIndex((el) => !el.classList.contains('is-disabled')), true);
    }

    function tandai(i, gulir) {
        if (!aktif) return;
        aktif.visible.forEach((el) => el.classList.remove('is-active'));
        aktif.index = i;
        const el = aktif.visible[i];
        if (!el) return;
        el.classList.add('is-active');
        (aktif.input || aktif.select).setAttribute('aria-activedescendant', el.id);
        if (gulir) el.scrollIntoView({ block: 'nearest' });
    }

    function geser(arah) {
        const n = aktif.visible.length;
        if (!n) return;
        let i = aktif.index;
        for (let k = 0; k < n; k++) {
            i = (i + arah + n) % n;
            if (!aktif.visible[i].classList.contains('is-disabled')) break;
        }
        tandai(i, true);
    }

    function posisikan() {
        if (!aktif) return;
        const { select, panel, list } = aktif;
        const r = select.getBoundingClientRect();
        if (r.width === 0 && r.height === 0) return tutup(false);
        const lebar = Math.max(r.width, 240);
        const bawah = window.innerHeight - r.bottom - 12;
        const atas = r.top - 12;
        const keAtas = bawah < 260 && atas > bawah;
        list.style.maxHeight = Math.max(160, Math.min(360, (keAtas ? atas : bawah) - (aktif.input ? 56 : 0) - 34)) + 'px';
        panel.style.minWidth = lebar + 'px';
        panel.style.left = Math.max(8, Math.min(r.left, window.innerWidth - panel.offsetWidth - 8)) + 'px';
        if (keAtas) {
            panel.style.top = '';
            panel.style.bottom = (window.innerHeight - r.top + 6) + 'px';
            panel.setAttribute('data-atas', '');
        } else {
            panel.style.bottom = '';
            panel.style.top = (r.bottom + 6) + 'px';
            panel.removeAttribute('data-atas');
        }
    }

    function buka(select, awal = '') {
        if (aktif && aktif.select === select) return;
        tutup(false);
        if (select.disabled) return;

        const items = bangunItem(select);
        const jumlah = items.filter((it) => it.option).length;
        const panel = document.createElement('div');
        panel.className = 'ss-panel';
        panel.id = 'ss-panel-' + (++urutId);
        panel.setAttribute('role', 'listbox');
        const label = select.getAttribute('aria-label') || select.labels?.[0]?.textContent?.trim() || 'Pilihan';
        panel.setAttribute('aria-label', label);

        let input = null;
        if (jumlah > CARI_MIN || awal) {
            const cari = document.createElement('div');
            cari.className = 'ss-search';
            cari.innerHTML = '<i class="fas fa-magnifying-glass"></i><input type="text" autocomplete="off" spellcheck="false" placeholder="Cari…" aria-label="Cari pilihan">';
            panel.appendChild(cari);
            input = cari.querySelector('input');
            input.value = awal;
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-controls', panel.id);
            input.setAttribute('aria-expanded', 'true');
        }
        const list = document.createElement('div');
        list.className = 'ss-list';
        panel.appendChild(list);
        if (input) {
            const foot = document.createElement('div');
            foot.className = 'ss-foot';
            foot.textContent = jumlah + ' pilihan · ↑↓ untuk memilih, Enter untuk konfirmasi';
            panel.appendChild(foot);
        }

        document.body.appendChild(panel);
        aktif = { select, panel, input, list, items, index: -1, visible: [] };
        select.classList.add('ss-open');
        select.setAttribute('aria-expanded', 'true');
        select.setAttribute('aria-controls', panel.id);

        render();
        posisikan();

        if (input) {
            input.addEventListener('input', render);
            input.addEventListener('keydown', kunciPanel);
            requestAnimationFrame(() => { input.focus(); input.setSelectionRange(input.value.length, input.value.length); });
        }
    }

    function tutup(fokusKembali = true) {
        if (!aktif) return;
        const { select, panel } = aktif;
        panel.remove();
        select.classList.remove('ss-open');
        select.setAttribute('aria-expanded', 'false');
        select.removeAttribute('aria-activedescendant');
        aktif = null;
        if (fokusKembali && document.contains(select)) select.focus({ preventScroll: true });
    }

    function pilih(option) {
        if (!aktif || option.disabled) return;
        const select = aktif.select;
        const berubah = select.value !== option.value || !option.selected;
        tutup();
        if (!berubah) return;
        select.value = option.value;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function kunciPanel(e) {
        if (!aktif) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); geser(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); geser(-1); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            const el = aktif.visible[aktif.index];
            if (el) pilih(el._option);
        } else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); tutup(); }
        else if (e.key === 'Tab') { tutup(true); }
    }

    // Klik pada select: cegah daftar bawaan, buka panel.
    document.addEventListener('mousedown', (e) => {
        const s = e.target.closest && e.target.closest('select');
        if (s && eligible(s)) {
            if (e.button !== 0) return;
            e.preventDefault();
            if (aktif && aktif.select === s) return tutup();
            s.focus({ preventScroll: true });
            buka(s);
            return;
        }
        if (aktif && !aktif.panel.contains(e.target)) tutup(false);
    }, true);

    // Keyboard pada select yang sedang fokus.
    document.addEventListener('keydown', (e) => {
        const s = e.target;
        if (!eligible(s) || s.disabled) return;
        if (aktif && aktif.select === s) return kunciPanel(e);
        if (['Enter', ' ', 'ArrowDown', 'ArrowUp', 'F4'].includes(e.key) || (e.altKey && e.key === 'ArrowDown')) {
            e.preventDefault();
            buka(s);
        } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            // Mengetik langsung pada select = mulai mencari.
            e.preventDefault();
            buka(s, e.key);
            if (aktif && aktif.input) render();
        }
    }, true);

    window.addEventListener('resize', posisikan);
    document.addEventListener('scroll', (e) => {
        if (aktif && !aktif.panel.contains(e.target)) posisikan();
    }, true);

    // Setelah Livewire memperbarui halaman: tutup panel bila select-nya hilang,
    // atau susun ulang daftar bila opsinya berubah.
    const daftarkan = () => window.Livewire.hook('morphed', () => {
        if (!aktif) return;
        if (!document.contains(aktif.select)) return tutup(false);
        aktif.items = bangunItem(aktif.select);
        render();
        posisikan();
    });
    window.Livewire ? daftarkan() : document.addEventListener('livewire:init', daftarkan);

    window.SmartSelect = { open: buka, close: tutup };
})();
</script>
