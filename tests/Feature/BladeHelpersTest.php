<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Period;
use App\Models\User;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\RatioEngine;
use App\Support\EntityContext;
use App\Support\ScoreStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Fungsi bantu Blade (app/Http/Helpers/helper.php) hanya meneruskan ke kelasnya,
 * dan view tidak lagi menyebut nama kelas apa pun.
 */
class BladeHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_view_refers_to_a_class_name(): void
    {
        $pelanggar = [];

        foreach (Finder::create()->files()->in(resource_path('views'))->exclude('vendor')->name('*.blade.php') as $berkas) {
            $isi = $berkas->getContents();
            if (preg_match('/\\\\App\\\\|::class/', $isi)) {
                $pelanggar[] = $berkas->getRelativePathname();
            }
        }

        $this->assertSame([], $pelanggar, 'View harus memakai fungsi bantu, bukan nama kelas: '.implode(', ', $pelanggar));
    }

    public function test_no_view_falls_back_to_the_browser_confirm_box(): void
    {
        $pelanggar = [];

        foreach (Finder::create()->files()->in(resource_path('views'))->exclude('vendor')->name('*.blade.php') as $berkas) {
            // Dialog bawaan peramban menampilkan nama domain dan tidak dapat
            // ditata; aplikasi ini memakai data-konfirmasi (lihat
            // partials/confirm-dialog.blade.php). Berkas partial itu sendiri
            // menyebut wire:confirm hanya di dalam keterangannya.
            if (str_contains($berkas->getRelativePathname(), 'confirm-dialog')) {
                continue;
            }

            if (preg_match('/wire:confirm|window\.confirm\(/', $berkas->getContents())) {
                $pelanggar[] = $berkas->getRelativePathname();
            }
        }

        $this->assertSame([], $pelanggar,
            'Pakai data-konfirmasi, bukan dialog bawaan peramban: '.implode(', ', $pelanggar));
    }

    public function test_text_never_points_at_a_menu_that_does_not_exist(): void
    {
        // Label menu yang benar-benar ada di sidebar.
        preg_match_all('/<p>([^<]+)<\/p>/', file_get_contents(resource_path('views/layouts/app.blade.php')), $cocok);
        $menu = array_values(array_unique(array_map(
            fn ($teks) => trim(preg_replace('/\s+/', ' ', html_entity_decode($teks))),
            $cocok[1]
        )));
        $this->assertContains('Target & Realisasi', $menu);

        $pelanggar = [];
        // Panduan di docs/ ikut diperiksa: isinya tampil di menu Dokumentasi
        // Metode, jadi nama menu yang salah di sana sama menyesatkannya.
        $berkas = Finder::create()->files()
            ->in([resource_path('views'), app_path('Livewire'), base_path('docs')])
            ->exclude('vendor')
            ->name(['*.blade.php', '*.php', '*.md']);

        foreach ($berkas as $b) {
            preg_match_all('/menu ([A-Z][A-Za-z0-9\/ &]{1,40})/u', html_entity_decode($b->getContents()), $sebutan);

            foreach ($sebutan[1] as $nama) {
                $nama = trim(preg_replace('/\s+/', ' ', $nama));

                // Kalimat biasanya berlanjut sesudah nama menunya ("menu Pos Akun
                // lebih dulu"), jadi cukup salah satu yang menjadi awalan.
                $dikenal = array_filter($menu, fn ($label) => str_starts_with($nama, $label) || str_starts_with($label, $nama));

                if ($dikenal === []) {
                    $pelanggar[] = $b->getRelativePathname().': "menu '.$nama.'"';
                }
            }
        }

        $this->assertSame([], $pelanggar,
            'Teks menyebut menu yang tidak ada di sidebar: '.implode(' | ', $pelanggar));
    }

    public function test_no_comment_leaks_onto_the_page(): void
    {
        $pelanggar = [];

        foreach (Finder::create()->files()->in(resource_path('views'))->exclude('vendor')->name('*.blade.php') as $berkas) {
            $isi = $berkas->getContents();
            $nama = $berkas->getRelativePathname();

            // Blade menutup komentar pada penutup yang pertama ditemukan, jadi
            // komentar bersarang membuat sisanya tercetak sebagai teks biasa.
            preg_match_all('/\{\{--(.*?)--\}\}/s', $isi, $komentar);

            foreach ($komentar[1] as $badan) {
                if (str_contains($badan, '{{--')) {
                    $pelanggar[] = $nama.' (komentar bersarang)';
                }
            }

            if (substr_count($isi, '{{--') !== substr_count($isi, '--}}')) {
                $pelanggar[] = $nama.' (pembuka dan penutup komentar tidak seimbang)';
            }
        }

        $this->assertSame([], $pelanggar,
            'Komentar Blade akan tercetak di halaman: '.implode(' | ', $pelanggar));
    }

    /**
     * Pemilih periode tidak boleh memanjang ke bawah seiring bertambahnya tahun.
     *
     * Susunannya: satu baris tab tahun, lalu satu kisi Januari–Desember untuk
     * tahun yang dipilih — bukan seluruh tahun ditumpuk berurutan.
     */
    public function test_the_period_picker_shows_one_year_at_a_time(): void
    {
        $this->seed(DatabaseSeeder::class);
        $erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        app(EntityContext::class)->use($erdigma->id);

        $pengguna = User::create([
            'name' => 'Admin', 'email' => 'periode@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => $erdigma->id,
        ]);
        $pengguna->assignRole('Super Admin');

        foreach (['2025-11', '2025-12', '2026-08', '2027-01'] as $p) {
            Period::firstOrCreate(['period' => $p], ['status' => 'OPEN', 'apex_score' => 0]);
        }

        $html = $this->actingAs($pengguna)->get(route('dashboard'))->assertOk()->getContent();

        // Satu tab per tahun …
        foreach (['2025', '2026', '2027'] as $tahun) {
            $this->assertStringContainsString('data-tab-tahun="'.$tahun.'"', $html);
            $this->assertStringContainsString('data-panel-tahun="'.$tahun.'"', $html);
        }

        // … dan hanya satu kisi yang terlihat; sisanya disembunyikan.
        $terlihat = preg_match_all('/data-panel-tahun="\d{4}"(?![^>]*hidden)/', $html);
        $this->assertSame(1, $terlihat, 'Hanya kisi tahun aktif yang boleh tampil.');

        // Tiap kisi utuh 12 bulan, termasuk bulan yang periodenya belum dibuat.
        $this->assertStringContainsString('belum ada', $html);

        // Tahun terbaru di kiri, makin ke kanan makin lama.
        preg_match_all('/data-tab-tahun="(\d{4})"/', $html, $cocok);
        $tahun = $cocok[1];
        $urut = $tahun;
        rsort($urut);
        $this->assertSame($urut, $tahun, 'Tab tahun harus urut dari yang terbaru.');

        // Barisnya digeser mendatar, bukan membungkus ke bawah — dengan banyak
        // tahun, membungkus membuat dropdown memanjang seperti sebelum ada tab.
        $gaya = file_get_contents(public_path('css/custom-app.css'));
        $baris = substr($gaya, strpos($gaya, '.nb-period-tabs {'), 400);
        $this->assertStringContainsString('flex-wrap: nowrap', $baris);
        $this->assertStringContainsString('overflow-x: auto', $baris);
    }

    /**
     * Berkas gaya dimuat dengan penanda versi.
     *
     * Tanpa itu peramban menyimpan CSS lama tanpa batas waktu: perubahan
     * tampilan sudah terpasang di server, tetapi pengguna tetap melihat yang
     * lama sampai menekan muat-ulang paksa.
     */
    public function test_the_stylesheet_is_cache_busted(): void
    {
        $this->seed(DatabaseSeeder::class);
        $erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        app(EntityContext::class)->use($erdigma->id);

        $pengguna = User::create([
            'name' => 'Admin', 'email' => 'gaya@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => $erdigma->id,
        ]);
        $pengguna->assignRole('Super Admin');

        $html = $this->actingAs($pengguna)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/css\/custom-app\.css\?v=\d+/', $html);

        // Penandanya ikut berubah saat berkasnya berubah.
        $versi = asset_versioned('css/custom-app.css');
        $this->assertSame('?v='.filemtime(public_path('css/custom-app.css')), substr($versi, strpos($versi, '?')));

        // Berkas yang tidak ada tidak membuat URL rusak.
        $this->assertStringNotContainsString('?v=', asset_versioned('css/tidak-ada.css'));
    }

    public function test_every_blade_template_still_compiles(): void
    {
        $diperiksa = 0;

        foreach (Finder::create()->files()->in(resource_path('views'))->exclude('vendor')->name('*.blade.php') as $berkas) {
            $php = Blade::compileString($berkas->getContents());
            $galat = null;

            try {
                // Hanya memeriksa sintaksis hasil kompilasi; kodenya tidak dijalankan.
                token_get_all($php, TOKEN_PARSE);
            } catch (\ParseError $e) {
                $galat = $e->getMessage();
            }

            $this->assertNull($galat, $berkas->getRelativePathname().': '.$galat);
            $diperiksa++;
        }

        $this->assertGreaterThan(20, $diperiksa);
    }

    public function test_format_and_label_helpers_delegate_to_their_classes(): void
    {
        $this->assertSame(RatioEngine::TANPA_TARGET, status_tanpa_target());
        $this->assertSame(AccountPosts::NERACA, post_kind('neraca'));
        $this->assertTrue(post_is_neraca(AccountPosts::NERACA));
        $this->assertTrue(post_is_hris(AccountPosts::HRIS_RATA));
        $this->assertTrue(post_is_hris_rata(AccountPosts::HRIS_RATA));
        $this->assertFalse(post_is_hris_rata(AccountPosts::HRIS_ALIRAN));
        $this->assertTrue(post_is_aliran(AccountPosts::ALIRAN));
        $this->assertSame(AccountPosts::kindLabel(AccountPosts::ALIRAN), post_kind_label(AccountPosts::ALIRAN));
        $this->assertSame('37,00%', ratio_format(37.0, '%'));
        $this->assertContains('PA01', ratio_posts('P1'));
        $this->assertSame(ScoreStatus::TERCAPAI, score_status(100.0));
        $this->assertSame(ScoreStatus::BELUM_LENGKAP, score_status(null, false));
        $this->assertSame(ScoreStatus::label(ScoreStatus::TERCAPAI), score_label(score_status(100.0)));
        $this->assertNotSame('', score_color(score_status(100.0)));
        $this->assertNotSame('', password_hint());
        $this->assertContains('Head', kpi_levels());
        $this->assertNotSame([], kpi_statuses());
        $this->assertSame('not_phased', f1_belum_difasing());
        $this->assertSame('no_actual', f1_tanpa_realisasi());

        // Salah ketik nama jenis pos akun harus berbunyi, bukan diam-diam jadi "aliran".
        $this->assertThrows(fn () => post_kind('alran'), \InvalidArgumentException::class);
        // Periode yang bulannya tidak dikenal tampil apa adanya.
        $this->assertSame('2026-13', period_label('2026-13'));
    }

    public function test_period_helpers_follow_the_active_period(): void
    {
        $this->seed(DatabaseSeeder::class);
        $erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        app(EntityContext::class)->use($erdigma->id);
        Period::create(['period' => '2026-09', 'status' => 'CLOSED', 'apex_score' => 0]);

        $this->assertSame(Period::active(), active_period());
        $this->assertSame('September 2026', period_label('2026-09'));
        $this->assertSame('SEP', month_short('2026-09'));
        $this->assertSame('Sep', month_abbr('2026-09')); // daftar periode memakai huruf kapital di awal saja
        $this->assertSame('Agustus', month_name('2026-08'));
        $this->assertTrue(period_closed('CLOSED'));
        $this->assertFalse(period_closed('OPEN'));
        $this->assertEqualsCanonicalizing(['2026-08', '2026-09'], periods_with_status()->pluck('period')->all());
    }

    public function test_entity_helpers_follow_the_logged_in_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $erdigma = Entity::where('code', 'ERDIGMA')->firstOrFail();
        $user = User::create(['name' => 'Admin', 'email' => 'helper@contoh.test', 'password' => bcrypt('x'), 'is_active' => true, 'entity_id' => $erdigma->id]);
        $user->assignRole('Super Admin');
        $this->actingAs($user);
        app(EntityContext::class)->use($erdigma->id);

        $this->assertSame($erdigma->id, active_entity()?->id);
        $this->assertFalse(can_switch_entity()); // terikat satu entitas
        $this->assertSame(['ERDIGMA'], switchable_entities()->pluck('code')->all());
        $this->assertStringEndsWith('logo%20aej.webp', (string) entity_logo_of('AEJ'));
        $this->assertNull(entity_logo_of(null));
    }
}
