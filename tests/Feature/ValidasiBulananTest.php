<?php

namespace Tests\Feature;

use App\Livewire\AccountBalances;
use App\Models\AccountBalance;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\RatioTarget;
use App\Models\User;
use App\Support\Bsc\RatioEngine;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Validasi bulanan oleh Finance entitas.
 *
 * Pos akun aliran disimpan sebagai nilai YTD, sehingga "angka bulan Agustus"
 * adalah selisihnya terhadap Juli. Tanpa itu, Finance tidak punya angka yang
 * dapat dibandingkan dengan laporan bulanan mereka sendiri — dan rasio bulan
 * yang buruk tertutup oleh bulan-bulan sebelumnya yang baik.
 */
class ValidasiBulananTest extends TestCase
{
    use RefreshDatabase;

    private RatioEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        app(EntityContext::class)->use(Entity::where('code', 'HERBATECH')->value('id'));
        $this->engine = app(RatioEngine::class);
    }

    private function isi(string $period, array $pos): void
    {
        foreach ($pos as $kode => $nilai) {
            AccountBalance::updateOrCreate(
                ['period' => $period, 'code' => $kode],
                ['amount' => $nilai[0], 'opening' => $nilai[1] ?? null]
            );
        }
    }

    /**
     * Inti persoalannya: margin Januari–Agustus tampak sehat, padahal Agustus
     * sendiri jual tanpa laba kotor sama sekali.
     */
    public function test_a_bad_month_hides_inside_a_healthy_cumulative_margin(): void
    {
        $this->isi('2026-07', ['PA01' => [700], 'PA02' => [420]]);
        $this->isi('2026-08', ['PA01' => [800], 'PA02' => [520]]);

        $kumulatif = $this->engine->evaluate('2026-08');
        $bulanan = $this->engine->evaluateMonthOnly('2026-08');

        $gpm = fn (array $hasil) => collect($hasil['rows'])->firstWhere('code', 'P1')['actual'];

        // Jan–Agu: laba kotor 280 dari penjualan 800 = 35%.
        $this->assertSame(35.0, round((float) $gpm($kumulatif), 2));
        // Agustus sendiri: penjualan 100, HPP 100 — tidak ada laba kotor.
        $this->assertSame(0.0, round((float) $gpm($bulanan), 2));
    }

    public function test_flow_posts_are_annualised_from_the_month_alone(): void
    {
        $this->isi('2026-07', ['PA01' => [700]]);
        $this->isi('2026-08', ['PA01' => [800]]);

        // Agustus 100 → disetahunkan × 12 (n = 1), bukan × 12 ÷ 8.
        $this->assertSame(1_200.0, $this->engine->evaluateMonthOnly('2026-08')['used']['PA01']);
        $this->assertSame(1_200.0, $this->engine->evaluate('2026-08')['used']['PA01']);
    }

    public function test_balance_posts_average_against_last_month_closing(): void
    {
        $this->isi('2026-07', ['PA05' => [100]]);
        $this->isi('2026-08', ['PA05' => [200, 50]]);

        // Bulanan: (saldo akhir Juli 100 + saldo akhir Agustus 200) ÷ 2.
        $this->assertSame(150.0, $this->engine->evaluateMonthOnly('2026-08')['used']['PA05']);
        // Kumulatif: (saldo awal tahun 50 + saldo akhir 200) ÷ 2.
        $this->assertSame(125.0, $this->engine->evaluate('2026-08')['used']['PA05']);
    }

    /**
     * Tanpa bulan pembanding, selisihnya tidak dapat dihitung. Memakai YTD apa
     * adanya akan diam-diam kembali ke dasar kumulatif — dasar yang justru
     * sedang diperiksa — jadi angkanya dikosongkan dan dilaporkan.
     */
    public function test_a_missing_previous_month_is_reported_not_guessed(): void
    {
        $this->isi('2026-08', ['PA01' => [800], 'PA02' => [520]]);

        $bulanan = $this->engine->evaluateMonthOnly('2026-08');

        $this->assertNull($bulanan['used']['PA01']);
        $this->assertContains('PA01', $bulanan['needs_previous']);
        $this->assertSame('2026-07', $bulanan['previous_period']);
    }

    public function test_january_needs_no_comparison_month(): void
    {
        $this->isi('2026-01', ['PA01' => [100]]);

        $bulanan = $this->engine->evaluateMonthOnly('2026-01');

        $this->assertSame(1_200.0, $bulanan['used']['PA01']);
        $this->assertSame([], $bulanan['needs_previous']);
        $this->assertNull($bulanan['previous_period']);
    }

    /** YTD yang menyusut bisa benar (pembalikan jurnal) atau salah input — harus dilihat. */
    public function test_a_shrinking_ytd_is_flagged(): void
    {
        $this->isi('2026-07', ['PA01' => [800]]);
        $this->isi('2026-08', ['PA01' => [700]]);

        $bulanan = $this->engine->evaluateMonthOnly('2026-08');

        $this->assertContains('PA01', $bulanan['negative']);
        $this->assertSame(-1_200.0, $bulanan['used']['PA01']);
    }

    /**
     * Penjagaan terpenting: validasi bulanan tidak boleh menulis apa pun.
     *
     * financial_ratios dibaca skor resmi entitas dan konsolidasi holding. Bila
     * dasar bulanan ikut tersimpan ke sana, sekali seseorang memeriksa satu
     * bulan, skor resmi entitas itu berubah.
     */
    public function test_month_only_evaluation_never_writes_anything(): void
    {
        $this->isi('2026-07', ['PA01' => [700], 'PA02' => [420]]);
        $this->isi('2026-08', ['PA01' => [800], 'PA02' => [520]]);
        RatioTarget::create(['year' => '2026', 'code' => 'P1', 'target' => 30]);

        $this->engine->materialize('2026-08');
        $tersimpan = FinancialRatio::where('period', '2026-08')->where('ratio_code', 'P1')->value('actual');

        $this->engine->evaluateMonthOnly('2026-08');

        $this->assertSame(
            (float) $tersimpan,
            (float) FinancialRatio::where('period', '2026-08')->where('ratio_code', 'P1')->value('actual'),
            'Rasio tersimpan harus tetap atas dasar kumulatif.'
        );
        $this->assertSame(35.0, round((float) $tersimpan, 2));
    }

    /* ------------------------------------------------ layar validasi Finance */

    private function finance(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $pengguna = User::create([
            'name' => 'Finance', 'email' => 'finance@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => Entity::where('code', 'HERBATECH')->value('id'),
        ]);
        $pengguna->assignRole('Super Admin');
        $this->actingAs($pengguna);
        app(EntityContext::class)->use($pengguna->entity_id);

        return $pengguna;
    }

    /**
     * Layar Pos Akun mengikuti cara baca periode: inilah tempat Finance
     * memeriksa satu bulan, jadi pratinjaunya harus bercerita tentang bulan itu.
     */
    public function test_the_account_post_screen_previews_the_selected_month_alone(): void
    {
        $this->finance();
        $this->isi('2026-07', ['PA01' => [700], 'PA02' => [420]]);
        $this->isi('2026-08', ['PA01' => [800], 'PA02' => [520]]);

        $gpm = fn (array $hasil) => round((float) collect($hasil['rows'])->firstWhere('code', 'P1')['actual'], 2);

        $kumulatif = Livewire::test(AccountBalances::class)->set('period', '2026-08')->viewData('hasil');
        $this->assertSame(35.0, $gpm($kumulatif));

        Period::setCumulative(false);
        $bulanan = Livewire::test(AccountBalances::class)->set('period', '2026-08')->viewData('hasil');
        $this->assertSame(0.0, $gpm($bulanan));
    }

    /** Kolom "Bulan ini" berguna juga saat membaca kumulatif, jadi selalu dihitung. */
    public function test_the_month_figure_is_available_even_while_reading_cumulative(): void
    {
        $this->finance();
        $this->isi('2026-07', ['PA01' => [700]]);
        $this->isi('2026-08', ['PA01' => [800]]);

        $bulanan = Livewire::test(AccountBalances::class)->set('period', '2026-08')->viewData('bulanan');

        $this->assertTrue(Period::isCumulative());
        $this->assertSame(100.0, $bulanan['inputs']['PA01']['amount']);
    }

    /**
     * Penjagaan terpenting di layar: menyimpan sambil memeriksa satu bulan tidak
     * boleh mengubah angka resmi entitas.
     */
    public function test_saving_while_inspecting_one_month_still_stores_the_cumulative_basis(): void
    {
        $this->finance();
        $this->isi('2026-07', ['PA01' => [700], 'PA02' => [420]]);
        $this->isi('2026-08', ['PA01' => [800], 'PA02' => [520]]);
        RatioTarget::create(['year' => '2026', 'code' => 'P1', 'target' => 30]);

        Period::setCumulative(false);
        Livewire::test(AccountBalances::class)->set('period', '2026-08')->call('save');

        $this->assertSame(
            35.0,
            round((float) FinancialRatio::where('period', '2026-08')->where('ratio_code', 'P1')->value('actual'), 2),
            'Rasio tersimpan harus tetap atas dasar kumulatif, apa pun mode bacanya.'
        );
    }

    public function test_the_screen_says_which_month_is_missing_instead_of_showing_a_wrong_figure(): void
    {
        $this->finance();
        $this->isi('2026-08', ['PA01' => [800], 'PA02' => [520]]);
        Period::setCumulative(false);

        $this->get(route('account-balances', ['period' => '2026-08']))
            ->assertOk()
            ->assertSee('Memeriksa bulan Agustus saja')
            ->assertSee('belum dapat dinilai per bulan')
            ->assertSee('2026-07');
    }

    public function test_the_pyramid_warns_that_its_apex_mixes_two_windows(): void
    {
        $this->finance();
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
        Period::setCumulative(false);

        $this->get(route('dashboard', ['selectedPeriod' => '2026-08']))
            ->assertOk()
            ->assertSee('bercampur dasar', false);
    }

    /**
     * Daftar Rasio Keuangan selalu dibaca dari hasil tersimpan, yaitu dasar
     * kumulatif. Pada mode bulan-saja hal itu harus dikatakan, bukan dibiarkan
     * terbaca sebagai rasio bulan itu.
     */
    public function test_the_ratio_list_admits_its_figures_are_cumulative(): void
    {
        $this->finance();
        $this->isi('2026-07', ['PA01' => [700], 'PA02' => [420]]);
        $this->isi('2026-08', ['PA01' => [800], 'PA02' => [520]]);
        RatioTarget::create(['year' => '2026', 'code' => 'P1', 'target' => 30]);
        // Halaman Rasio hanya mau pindah ke periode yang sudah dibuat.
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
        app(RatioEngine::class)->materialize('2026-08');

        Period::setCumulative(false);

        $this->get(route('financial-ratios', ['period' => '2026-08']))
            ->assertOk()
            ->assertSee('dasar kumulatif')
            ->assertSee('rasio Agustus saja ada di Pos Akun');
    }

    /**
     * Halaman benar-benar dirender pada jalur normal: bulan pembanding lengkap,
     * satu pos menyusut. Kolom "Bulan ini" dan peringatannya harus muncul.
     */
    public function test_the_screen_shows_the_month_figure_and_warns_about_a_shrinking_post(): void
    {
        $this->finance();
        $this->isi('2026-07', ['PA01' => [700], 'PA03' => [200]]);
        $this->isi('2026-08', ['PA01' => [800], 'PA03' => [150]]);
        Period::setCumulative(false);

        $halaman = $this->get(route('account-balances', ['period' => '2026-08']))->assertOk();

        // Penjualan Agustus = 800 − 700.
        $halaman->assertSee('Bulan ini')
            ->assertSee('100')
            ->assertSee('YTD-nya menyusut')
            ->assertSee('Beban usaha', false);
    }
}
