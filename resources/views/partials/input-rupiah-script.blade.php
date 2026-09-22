<script>
    // Format isian uang Indonesia: titik pemisah ribuan, koma desimal, awalan "Rp".
    // Nilai yang ditautkan ke Livewire (x-modelable "nilai") tetap angka murni.
    document.addEventListener('alpine:init', () => {
        const ribuan = (s) => s.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        window.Alpine.data('inputRupiah', (prefix = 'Rp', desimal = 2) => ({
            nilai: null,
            teks: '',

            init() {
                this.teks = this.format(this.nilai);
                // Nilai yang diubah server (mis. "Bagi rata 12 bulan") ikut diformat.
                this.$watch('nilai', (v) => {
                    if (!this.sama(this.angka(this.teks), v)) {
                        this.teks = this.format(v);
                    }
                });
            },

            awalan() {
                return prefix ? prefix + ' ' : '';
            },

            sama(a, b) {
                const kosong = (x) => x === null || x === undefined || x === '';
                if (kosong(a) || kosong(b)) return kosong(a) && kosong(b);
                return Number(a) === Number(b);
            },

            /** "Rp 1.000.000,5" → "1000000.5" (string angka) atau "". */
            angka(teks) {
                const bersih = String(teks ?? '').replace(/[^\d,-]/g, '');
                const neg = bersih.startsWith('-');
                const [bulat = '', ...sisa] = bersih.replace(/-/g, '').split(',');
                const pecahan = sisa.join('').slice(0, desimal);
                if (bulat === '' && pecahan === '') return '';
                return (neg ? '-' : '') + (bulat.replace(/^0+(?=\d)/, '') || '0') + (pecahan ? '.' + pecahan : '');
            },

            /** Angka dari server → teks tampilan. */
            format(v) {
                if (v === null || v === undefined || v === '' || isNaN(Number(v))) return '';
                const n = Number(v);
                const neg = n < 0;
                const [bulat, pecahan] = String(Math.abs(n)).split('.');
                const p = pecahan ? pecahan.slice(0, desimal) : '';
                return this.awalan() + (neg ? '-' : '') + ribuan(bulat) + (p ? ',' + p : '');
            },

            ketik(e) {
                const el = e.target;
                if (e.inputType === 'insertFromPaste') {
                    const t = el.value.replace(/^\s*Rp\s*/i, '').trim();
                    if (/^-?\d+\.\d{1,2}$/.test(t)) el.value = t.replace('.', ',');
                }
                const pos = el.selectionStart ?? el.value.length;
                const hitung = (s) => s.replace(/[^\d,]/g, '').length;
                const sebelum = hitung(el.value.slice(0, pos));

                const bersih = el.value.replace(/[^\d,-]/g, '');
                const neg = bersih.startsWith('-');
                let [bulat = '', ...sisa] = bersih.replace(/-/g, '').split(',');
                const adaKoma = sisa.length > 0 && desimal > 0;
                const pecahan = sisa.join('').slice(0, desimal);
                bulat = bulat.replace(/^0+(?=\d)/, '');

                if (bulat === '' && !adaKoma) {
                    this.teks = neg ? '-' : '';
                    el.value = this.teks;
                    this.nilai = '';
                    return;
                }

                const tampil = this.awalan() + (neg ? '-' : '') + ribuan(bulat || '0') + (adaKoma ? ',' + pecahan : '');
                this.teks = tampil;
                el.value = tampil;
                this.nilai = (neg ? '-' : '') + (bulat || '0') + (pecahan ? '.' + pecahan : '');

                // Kursor tetap di posisi digit yang sama setelah titik ribuan disisipkan.
                let i = this.awalan().length + (neg ? 1 : 0);
                for (let n = 0; i < tampil.length && n < sebelum; i++) {
                    if (/[\d,]/.test(tampil[i])) n++;
                }
                el.setSelectionRange(i, i);
            },

            rapikan() {
                this.teks = this.format(this.nilai);
            },
        }));
    });
</script>
