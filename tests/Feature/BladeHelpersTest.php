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
