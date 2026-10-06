<?php

namespace Tests\Feature;

use App\Livewire\BscDashboard;
use App\Livewire\BscWiring;
use App\Models\Entity;
use App\Models\Period;
use App\Models\RevenuePlan;
use App\Models\RevenueTarget;
use App\Models\User;
use App\Support\Bsc\Scorecard;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dua cara membaca periode.
 *
 * Kumulatif menjawab "sampai bulan ini sudah sampai mana?"; bulan saja menjawab
 * "bulan ini sendiri sudah benar atau belum?". Yang kedua dibutuhkan untuk
 * memvalidasi satu bulan tanpa tertutup capaian bulan-bulan sebelumnya.
 */
class PeriodeKumulatifTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(EntityContext::class)->use(Entity::where('code', 'HERBATECH')->value('id'));

        RevenuePlan::create(['year' => '2026', 'approved_target' => 1_200_000_000]);

        // Juli tepat sasaran, Agustus meleset jauh. Kumulatif menutupi Agustus.
        Period::create(['period' => '2026-07', 'status' => 'OPEN', 'apex_score' => 0]);
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
        RevenueTarget::create(['period' => '2026-07', 'target' => 100_000_000, 'actual' => 100_000_000]);
        RevenueTarget::create(['period' => '2026-08', 'target' => 100_000_000, 'actual' => 50_000_000]);
    }

    public function test_cumulative_is_the_default(): void
    {
        $this->assertTrue(Period::isCumulative());

        // (100 jt + 50 jt) ÷ (100 jt + 100 jt) = 75%
        $this->assertSame(75.0, RevenueTarget::cumulativeAchievement('2026-08'));
    }

    public function test_turning_it_off_scores_the_selected_month_alone(): void
    {
        Period::setCumulative(false);

        // 50 jt ÷ 100 jt = 50% — capaian Agustus sendiri, tanpa tertolong Juli.
        $this->assertSame(50.0, RevenueTarget::cumulativeAchievement('2026-08'));

        $rincian = RevenueTarget::cumulative('2026-08');
        $this->assertSame(100_000_000.0, $rincian['target_ytd']);
        $this->assertSame(50_000_000.0, $rincian['actual_ytd']);
        $this->assertSame(1, $rincian['months_actual']);
        $this->assertFalse($rincian['cumulative']);
    }

    public function test_the_mode_can_be_asked_for_explicitly_regardless_of_the_session(): void
    {
        Period::setCumulative(false);

        // Pemanggil yang memang butuh dasar resmi tetap bisa memintanya.
        $this->assertSame(75.0, RevenueTarget::cumulativeAchievement('2026-08', true));
        $this->assertSame(50.0, RevenueTarget::cumulativeAchievement('2026-08', false));
    }

    /**
     * Mode bulan-saja hanya cara MELIHAT.
     *
     * Skor yang tersimpan di tabel periode dipakai riwayat dan konsolidasi
     * holding, jadi tidak boleh ikut berubah ketika seseorang mematikan centang.
     */
    public function test_the_stored_apex_score_never_follows_the_viewing_mode(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $pengguna = User::create([
            'name' => 'Admin', 'email' => 'kumulatif@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => Entity::where('code', 'HERBATECH')->value('id'),
        ]);
        $pengguna->assignRole('Super Admin');
        $this->actingAs($pengguna);
        app(EntityContext::class)->use($pengguna->entity_id);

        Livewire::test(BscDashboard::class)->set('selectedPeriod', '2026-08');
        $tersimpan = (float) Period::where('period', '2026-08')->value('apex_score');
        $this->assertGreaterThan(0, $tersimpan);

        Period::setCumulative(false);
        Livewire::test(BscDashboard::class)->set('selectedPeriod', '2026-08');

        $this->assertSame(
            $tersimpan,
            (float) Period::where('period', '2026-08')->value('apex_score'),
            'Skor tersimpan harus tetap atas dasar kumulatif.'
        );
    }

    /**
     * Ringkasan resmi entitas tidak boleh ikut mode tampilan.
     *
     * Ringkasan inilah yang dibaca holding. Bila ikut terbawa, konsolidasi akan
     * mencampur dua dasar: entitas yang dibaca langsung dari database memakai
     * bulan-saja, sedangkan yang dibaca lewat API (tanpa sesi) tetap kumulatif.
     */
    public function test_the_official_entity_summary_stays_cumulative(): void
    {
        $kumulatif = Scorecard::forPeriod('2026-08');

        Period::setCumulative(false);
        $sesudah = Scorecard::forPeriod('2026-08');

        $this->assertSame(75.0, $kumulatif['revenue']);
        $this->assertSame($kumulatif['revenue'], $sesudah['revenue'], 'Skor resmi harus tetap kumulatif.');
    }

    /**
     * Halaman Wiring menampilkan satu cerita.
     *
     * Dulu F1-nya mengikuti mode tetapi angka target/realisasi di sebelahnya
     * selalu Jan s.d. periode — dua angka yang tidak saling cocok di satu baris.
     */
    public function test_the_wiring_page_figures_follow_the_same_window_as_its_f1(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $pengguna = User::create([
            'name' => 'Admin', 'email' => 'wiring@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => Entity::where('code', 'HERBATECH')->value('id'),
        ]);
        $pengguna->assignRole('Super Admin');
        $this->actingAs($pengguna);
        app(EntityContext::class)->use($pengguna->entity_id);

        Period::setCumulative(false);

        $revenue = Livewire::test(BscWiring::class)
            ->set('selectedPeriod', '2026-08')
            ->viewData('revenue');

        // Agustus saja: 50 jt dari 100 jt — bukan 150 jt dari 200 jt.
        $this->assertSame(100_000_000.0, $revenue['target_ytd']);
        $this->assertSame(50_000_000.0, $revenue['actual_ytd']);
        $this->assertSame(50.0, $revenue['f1']);
    }

    public function test_the_choice_survives_across_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $pengguna = User::create([
            'name' => 'Admin', 'email' => 'sesi@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => Entity::where('code', 'HERBATECH')->value('id'),
        ]);
        $pengguna->assignRole('Super Admin');
        $this->actingAs($pengguna);

        // Mematikan centang lewat tombolnya …
        $this->post(route('period.cumulative'), ['cumulative' => 0])->assertRedirect();
        $this->assertFalse(Period::isCumulative());

        // … dan menyalakannya kembali.
        $this->post(route('period.cumulative'), ['cumulative' => 1])->assertRedirect();
        $this->assertTrue(Period::isCumulative());
    }

    public function test_the_dropdown_explains_which_mode_is_active(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $pengguna = User::create([
            'name' => 'Admin', 'email' => 'tampilan@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => Entity::where('code', 'HERBATECH')->value('id'),
        ]);
        $pengguna->assignRole('Super Admin');
        $this->actingAs($pengguna);
        app(EntityContext::class)->use($pengguna->entity_id);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Hitung kumulatif sejak Januari')
            ->assertSee('capaian dijumlah Januari');

        Period::setCumulative(false);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('hanya angka bulan terpilih yang dinilai')
            // Dasar rasio dikatakan terus terang, beserta di mana memeriksanya.
            ->assertSee('Rasio bulan itu sendiri diperiksa di', false)
            ->assertSee('skor F2 di piramida', false);
    }
}
