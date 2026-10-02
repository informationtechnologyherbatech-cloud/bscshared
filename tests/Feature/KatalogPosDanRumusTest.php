<?php

namespace Tests\Feature;

use App\Livewire\AccountBalances;
use App\Livewire\RatioCatalog;
use App\Models\AccountBalance;
use App\Models\AccountPostDefinition;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\RatioDefinition;
use App\Models\User;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\Formula;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pos akun dan rasio kini DATA, bukan daftar tetap di dalam kode: entitas dapat
 * menambah pos akun, menambah rasio, dan menyesuaikan rumusnya.
 *
 * Yang paling dijaga di sini adalah janji terakhir — rumus yang tertulis di
 * Katalog Rasio benar-benar dipakai menghitung, bukan sekadar keterangan.
 */
class KatalogPosDanRumusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::where('email', 'superadmin@emc.co.id')->firstOrFail());
        app(EntityContext::class)->use((int) Entity::where('code', 'HERBATECH')->value('id'));
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
        AccountPosts::forget();
    }

    private function isiPosAkun(): void
    {
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA01', 'amount' => 800_000_000]);
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA02', 'amount' => 500_000_000]);
        app(RatioEngine::class)->materialize('2026-08');
    }

    /* ------------------------------------------------------------ mesin rumus */

    public function test_the_formula_engine_reads_arithmetic_the_way_people_write_it(): void
    {
        $nilai = ['PA01' => 1000.0, 'PA02' => 600.0, 'PA05' => 200.0];

        $this->assertSame(400.0, Formula::evaluate('PA01 - PA02', $nilai));
        $this->assertSame(40.0, Formula::evaluate('(PA01 - PA02) / PA01 * 100', $nilai));
        // Tanda yang biasa disalin dari Excel atau diketik langsung.
        $this->assertSame(40.0, Formula::evaluate('(PA01 − PA02) ÷ PA01 × 100', $nilai));
        // Koma desimal gaya Indonesia.
        $this->assertSame(500.0, Formula::evaluate('PA01 * 0,5', $nilai));
        $this->assertSame(-600.0, Formula::evaluate('-PA02', $nilai));
        $this->assertSame(5.0, Formula::evaluate('PA01 / PA05', $nilai));
    }

    public function test_an_empty_post_or_a_zero_denominator_leaves_the_ratio_empty(): void
    {
        $nilai = ['PA01' => 1000.0, 'PA02' => null, 'PA05' => 0.0];

        // Pos yang belum diisi: rasionya kosong, bukan nol — supaya tidak ikut
        // diskor sebagai capaian buruk.
        $this->assertNull(Formula::evaluate('PA01 - PA02', $nilai));
        $this->assertNull(Formula::evaluate('PA01 / PA05', $nilai));
        $this->assertNull(Formula::evaluate('PA01 / PA99', $nilai));
    }

    public function test_a_broken_formula_says_what_is_wrong_in_plain_words(): void
    {
        $dikenal = ['PA01', 'PA02'];

        $this->assertNull(Formula::validate('PA01 / PA02', $dikenal));
        $this->assertStringContainsString('belum diisi', (string) Formula::validate('   ', $dikenal));
        $this->assertStringContainsString('PA77', (string) Formula::validate('PA01 / PA77', $dikenal));
        $this->assertStringContainsString('kurung', (string) Formula::validate('(PA01 / PA02', $dikenal));
        $this->assertStringContainsString('terputus', (string) Formula::validate('PA01 /', $dikenal));
        $this->assertStringContainsString('pos akun', (string) Formula::validate('1 + 1', $dikenal));
    }

    public function test_formulas_that_point_at_each_other_stop_instead_of_looping(): void
    {
        $rumus = ['X' => '100 / Y', 'Y' => '100 / X'];

        // Tanpa pengaman, keduanya akan saling memanggil tanpa henti.
        $this->assertNull(Formula::evaluate('X', [], Formula::resolver($rumus, [])));
    }

    /**
     * Mesin rumus harus menghasilkan angka yang sama persis dengan perhitungan
     * baku workbook untuk ke-19 rasio bawaan — termasuk saat ada pos kosong dan
     * penyebut nol.
     */
    public function test_the_engine_reproduces_the_workbook_for_all_builtin_ratios(): void
    {
        $skenario = [
            'lengkap' => ['PA01' => 1000.0, 'PA02' => 600.0, 'PA03' => 200.0, 'PA04' => 150.0, 'PA05' => 120.0,
                'PA06' => 180.0, 'PA07' => 90.0, 'PA08' => 75.0, 'PA09' => 400.0, 'PA10' => 250.0,
                'PA11' => 900.0, 'PA12' => 350.0, 'PA13' => 550.0, 'PA14' => 300.0, 'PA15' => 25.0, 'PA16' => 40000.0],
            'rugi' => ['PA01' => 500.0, 'PA02' => 700.0, 'PA03' => 200.0, 'PA04' => 150.0, 'PA05' => 120.0,
                'PA06' => 180.0, 'PA07' => 90.0, 'PA08' => 75.0, 'PA09' => 400.0, 'PA10' => 250.0,
                'PA11' => 900.0, 'PA12' => 350.0, 'PA13' => -550.0, 'PA14' => 300.0, 'PA15' => 25.0, 'PA16' => 40000.0],
            'penyebut nol' => ['PA01' => 1000.0, 'PA02' => 600.0, 'PA03' => 200.0, 'PA04' => 0.0, 'PA05' => 0.0,
                'PA06' => 0.0, 'PA07' => 0.0, 'PA08' => 75.0, 'PA09' => 400.0, 'PA10' => 0.0,
                'PA11' => 0.0, 'PA12' => 350.0, 'PA13' => 0.0, 'PA14' => 0.0, 'PA15' => 0.0, 'PA16' => 0.0],
            'setengah kosong' => ['PA01' => 1000.0, 'PA02' => null, 'PA03' => 200.0, 'PA04' => null, 'PA05' => 120.0,
                'PA06' => null, 'PA07' => 90.0, 'PA08' => null, 'PA09' => 400.0, 'PA10' => null,
                'PA11' => 900.0, 'PA12' => null, 'PA13' => 550.0, 'PA14' => null, 'PA15' => 25.0, 'PA16' => null],
        ];

        foreach ($skenario as $nama => $angka) {
            $p = AccountPosts::withDerived($angka);

            foreach (array_keys(RatioLibrary::all()) as $kode) {
                $mesin = RatioLibrary::compute($kode, $p);
                $acuan = RatioLibrary::referenceCompute($kode, $p);

                if ($acuan === null) {
                    $this->assertNull($mesin, "$kode pada skenario $nama");
                } else {
                    $this->assertEqualsWithDelta($acuan, $mesin, 1e-9, "$kode pada skenario $nama");
                }
            }
        }
    }

    /* ------------------------------------------------------- katalog pos akun */

    public function test_an_entity_starts_with_the_sixteen_workbook_posts(): void
    {
        $this->assertCount(16, AccountPosts::all());
        $this->assertSame(array_keys(AccountPosts::builtins()), array_keys(AccountPosts::all()));
        $this->assertSame(16, AccountPostDefinition::count());
    }

    public function test_a_new_post_can_be_added_and_is_filled_in_on_the_same_page(): void
    {
        Livewire::test(AccountBalances::class, ['period' => '2026-08'])
            ->call('newPost')
            ->assertSet('formCode', 'PA17')
            ->set('formName', 'Beban pemasaran digital')
            ->set('formKind', AccountPosts::ALIRAN)
            ->set('formSource', 'GL')
            ->call('savePost')
            ->assertHasNoErrors()
            // Pos baru langsung punya kolom isian di halaman yang sama.
            ->assertSet('values.PA17.amount', '');

        AccountPosts::forget();
        $this->assertArrayHasKey('PA17', AccountPosts::all());
        $this->assertSame('Beban pemasaran digital', AccountPosts::nameOf('PA17'));
    }

    public function test_a_builtin_post_keeps_its_code_and_kind_but_can_be_renamed(): void
    {
        Livewire::test(AccountBalances::class, ['period' => '2026-08'])
            ->call('editPost', 'PA01')
            ->set('formName', 'Penjualan bersih (setelah retur)')
            ->call('savePost')
            ->assertHasNoErrors();

        AccountPosts::forget();
        $this->assertSame('Penjualan bersih (setelah retur)', AccountPosts::nameOf('PA01'));
        // Jenisnya tetap, karena rumus bawaan bergantung padanya.
        $this->assertSame(AccountPosts::ALIRAN, AccountPosts::kind('PA01'));
    }

    public function test_a_post_still_used_by_a_formula_cannot_be_switched_off(): void
    {
        Livewire::test(AccountBalances::class, ['period' => '2026-08'])
            ->call('togglePost', 'PA01');

        // Tetap aktif: PA01 membentuk laba kotor, jadi rumus bawaan ikut mati bila dimatikan.
        $this->assertTrue(AccountPostDefinition::where('code', 'PA01')->first()->is_active);
        $this->assertContains('PA01', array_keys(AccountPosts::all()));
    }

    public function test_a_builtin_post_cannot_be_deleted(): void
    {
        Livewire::test(AccountBalances::class, ['period' => '2026-08'])
            ->call('deletePost', 'PA05');

        $this->assertTrue(AccountPostDefinition::where('code', 'PA05')->exists());
        $this->assertContains('PA05', array_keys(AccountPosts::all()));
    }

    public function test_an_unused_custom_post_can_be_deleted_together_with_its_numbers(): void
    {
        AccountPostDefinition::create([
            'code' => 'PA90', 'name' => 'Pos percobaan', 'kind' => AccountPosts::ALIRAN,
            'source' => 'GL', 'is_active' => true, 'is_builtin' => false, 'sort' => 90,
        ]);
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA90', 'amount' => 123.0]);

        Livewire::test(AccountBalances::class, ['period' => '2026-08'])->call('deletePost', 'PA90');

        $this->assertFalse(AccountPostDefinition::where('code', 'PA90')->exists());
        $this->assertFalse(AccountBalance::where('code', 'PA90')->exists());
    }

    /* --------------------------------------------------------- katalog rasio */

    public function test_a_custom_ratio_built_on_a_custom_post_really_drives_the_number(): void
    {
        // Pos tambahan khas entitas: beban pemasaran.
        AccountPostDefinition::create([
            'code' => 'PA17', 'name' => 'Beban pemasaran', 'kind' => AccountPosts::ALIRAN,
            'source' => 'GL', 'is_active' => true, 'is_builtin' => false, 'sort' => 17,
        ]);
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA01', 'amount' => 800_000_000]);
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA17', 'amount' => 80_000_000]);

        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('newRatio')
            ->set('formCode', 'R1')
            ->set('formName', 'Beban pemasaran terhadap penjualan')
            ->set('formGroup', 'Efisiensi')
            ->set('formUnit', '%')
            ->set('formPolarity', RatioLibrary::TURUN)
            ->set('formWeight', '5')
            ->set('formExpression', 'PA17 / PA01 * 100')
            ->call('saveRatio')
            ->assertHasNoErrors();

        $rasio = FinancialRatio::where('period', '2026-08')->where('ratio_code', 'R1')->first();

        $this->assertNotNull($rasio, 'Rasio buatan sendiri harus ikut terhitung.');
        $this->assertEqualsWithDelta(10.0, (float) $rasio->actual, 1e-9); // 80 jt ÷ 800 jt × 100
        $this->assertSame('Efisiensi', $rasio->category);
        $this->assertSame(RatioLibrary::TURUN, $rasio->polarity);
    }

    public function test_changing_a_builtin_formula_changes_the_number_it_produces(): void
    {
        $this->isiPosAkun();

        // Bawaan: GPM = (800 − 500) ÷ 800 × 100 = 37,5%.
        $this->assertEqualsWithDelta(
            37.5,
            (float) FinancialRatio::where('period', '2026-08')->where('ratio_code', 'P1')->value('actual'),
            1e-9
        );

        // Entitas memutuskan GPM-nya dihitung terhadap HPP, bukan penjualan.
        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('editRatio', 'P1')
            ->set('formExpression', 'LK / PA02 * 100')
            ->call('saveRatio')
            ->assertHasNoErrors();

        // (800 − 500) ÷ 500 × 100 = 60% — angkanya ikut rumus barunya.
        $this->assertEqualsWithDelta(
            60.0,
            (float) FinancialRatio::where('period', '2026-08')->where('ratio_code', 'P1')->value('actual'),
            1e-9
        );
    }

    public function test_the_readable_formula_is_rewritten_from_the_one_that_is_calculated(): void
    {
        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('editRatio', 'P1')
            ->set('formExpression', 'LK / PA02 * 100')
            ->call('saveRatio');

        // Keterangan di layar dibangun dari rumusnya sendiri, jadi tidak mungkin
        // menjelaskan perhitungan yang berbeda.
        $this->assertSame('Laba kotor ÷ HPP × 100', RatioDefinition::where('code', 'P1')->value('formula'));
    }

    public function test_a_formula_with_an_unknown_code_or_pointing_at_itself_is_refused(): void
    {
        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('editRatio', 'P1')
            ->set('formExpression', 'PA99 / PA01')
            ->call('saveRatio')
            ->assertHasErrors('formExpression');

        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('editRatio', 'P1')
            ->set('formExpression', 'P1 * 2')
            ->call('saveRatio')
            ->assertHasErrors('formExpression');

        // Rumus aslinya tidak tersentuh.
        $this->assertSame('LK / PA01 * 100', RatioDefinition::where('code', 'P1')->value('expression'));
    }

    public function test_the_editor_tries_the_formula_on_real_numbers_before_it_is_saved(): void
    {
        $this->isiPosAkun();

        $komponen = Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('newRatio')
            ->set('formExpression', 'PA01 / PA02');

        $pratinjau = $komponen->instance()->preview();

        $this->assertSame('2026-08', $pratinjau['period']);
        $this->assertEqualsWithDelta(1.6, $pratinjau['value'], 1e-9); // 800 ÷ 500
        $this->assertStringContainsString('÷', $pratinjau['arithmetic']);
        $this->assertNull($pratinjau['error']);
    }

    public function test_a_builtin_ratio_cannot_be_deleted_but_a_custom_one_can(): void
    {
        Livewire::test(RatioCatalog::class, ['year' => '2026'])->call('deleteRatio', 'P1');
        $this->assertTrue(RatioDefinition::where('code', 'P1')->exists());

        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('newRatio')
            ->set('formCode', 'R9')
            ->set('formName', 'Percobaan')
            ->set('formExpression', 'PA01 / PA11')
            ->call('saveRatio')
            ->call('deleteRatio', 'R9');

        $this->assertFalse(RatioDefinition::where('code', 'R9')->exists());
    }

    public function test_a_custom_ratio_can_be_claimed_by_a_kpi_like_any_other(): void
    {
        // Peta pos akun & cascade KPI bertanya "rasio ini dibentuk pos apa?".
        // Rasio bawaan tidak boleh berubah jawabannya …
        $this->assertSame(['PA09', 'PA10'], RatioLibrary::postsOf('L1'));
        $this->assertEqualsCanonicalizing(['PA02', 'PA05'], RatioLibrary::postsOf('A4')); // 365 ÷ A1

        AccountPostDefinition::create([
            'code' => 'PA17', 'name' => 'Beban pemasaran', 'kind' => AccountPosts::ALIRAN,
            'source' => 'GL', 'is_active' => true, 'is_builtin' => false, 'sort' => 17,
        ]);
        RatioDefinition::create([
            'code' => 'R1', 'name' => 'Beban pemasaran terhadap penjualan', 'ratio_group' => 'Efisiensi',
            'formula' => 'Beban pemasaran ÷ Penjualan × 100', 'expression' => 'PA17 / PA01 * 100',
            'unit' => '%', 'polarity' => RatioLibrary::TURUN, 'weight' => 5,
            'is_active' => true, 'is_builtin' => false, 'sort' => 90,
        ]);

        // … dan rasio buatan sendiri harus ikut terbaca.
        $this->assertEqualsCanonicalizing(['PA17', 'PA01'], RatioLibrary::postsOf('R1'));
        $this->assertSame('Beban pemasaran terhadap penjualan', RatioLibrary::impactName('R1'));
    }

    public function test_changing_a_formula_also_changes_which_posts_drive_the_ratio(): void
    {
        $this->assertEqualsCanonicalizing(['PA09', 'PA05', 'PA10'], RatioLibrary::postsOf('L2'));

        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('editRatio', 'L2')
            ->set('formExpression', 'PA08 / PA10')
            ->call('saveRatio')
            ->assertHasNoErrors();

        // Pos kas ikut masuk begitu rumusnya memakainya.
        $this->assertContains('PA08', RatioLibrary::postsOf('L2'));
    }

    public function test_the_form_offers_every_group_and_lets_a_new_one_be_named(): void
    {
        $komponen = Livewire::test(RatioCatalog::class, ['year' => '2026'])->call('newRatio');

        // Kelima kelompok baku harus dapat dipilih, bukan hanya yang pertama.
        foreach (['Profitabilitas', 'Aktivitas', 'Produktivitas', 'Likuiditas', 'Solvabilitas'] as $kelompok) {
            $komponen->assertSee($kelompok);
        }

        $komponen->set('formCode', 'R5')
            ->set('formName', 'Beban pemasaran terhadap penjualan')
            ->set('formGroup', RatioCatalog::KELOMPOK_BARU)
            ->set('formGroupNew', '')
            ->set('formExpression', 'PA04 / PA01 * 100')
            ->call('saveRatio')
            // Memilih "kelompok baru" tanpa menamainya harus berbunyi …
            ->assertHasErrors('formGroupNew')
            ->set('formGroupNew', 'Efisiensi')
            ->call('saveRatio')
            ->assertHasNoErrors();

        $this->assertSame('Efisiensi', RatioDefinition::where('code', 'R5')->value('ratio_group'));
    }

    public function test_the_formula_being_typed_is_shown_in_plain_words(): void
    {
        $komponen = Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('newRatio')
            ->set('formExpression', '(PA09 - PA05) / PA10');

        $this->assertSame(
            '(Aset lancar − Persediaan) ÷ Liabilitas lancar',
            $komponen->instance()->readable()
        );

        // Rumus yang belum sah tidak menampilkan kalimat yang menyesatkan.
        $komponen->set('formExpression', '(PA09 - PA05');
        $this->assertSame('', $komponen->instance()->readable());
    }

    public function test_every_code_in_the_formula_helper_can_be_used(): void
    {
        $bantuan = Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('newRatio')
            ->instance()->codeHelp();

        $this->assertArrayHasKey('LK', $bantuan);
        $this->assertArrayHasKey('PA01', $bantuan);
        $this->assertArrayHasKey('A1', $bantuan);

        // Semua yang ditawarkan harus benar-benar diterima mesin rumus.
        foreach (array_keys($bantuan) as $kode) {
            $this->assertNull(
                Formula::validate($kode.' / 2', array_keys($bantuan)),
                'Kode '.$kode.' ditawarkan tetapi ditolak rumus.'
            );
        }
    }

    public function test_a_formula_can_be_built_by_clicking_instead_of_typing(): void
    {
        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('newRatio')
            ->call('insertCode', '(')
            ->call('insertCode', 'PA09')
            ->call('insertCode', '-')
            ->call('insertCode', 'PA05')
            ->call('insertCode', ')')
            ->call('insertCode', '/')
            ->call('insertCode', 'PA10')
            // Spasi dipasang sendiri, dan kurung tidak diberi jarak yang janggal.
            ->assertSet('formExpression', '(PA09 - PA05) / PA10');

        // Menyisipkan ke rumus yang sedang diketik tidak menimpa ketikannya.
        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('newRatio')
            ->set('formExpression', 'PA01 /')
            ->call('insertCode', 'PA02')
            ->assertSet('formExpression', 'PA01 / PA02');
    }

    public function test_the_data_source_is_spelled_out_not_just_abbreviated(): void
    {
        // "GL" sendirian tidak berarti apa-apa bagi yang mengisi.
        $this->assertStringContainsString('Buku besar', AccountPosts::sourceLabel('GL'));
        $this->assertStringContainsString('kepegawaian', AccountPosts::sourceLabel('HRIS'));
        $this->assertArrayHasKey('GL / HRIS', AccountPosts::sources());

        // Sumber di luar daftar tetap tampil apa adanya, bukan kosong.
        $this->assertSame('Lainnya', AccountPosts::sourceLabel('Lainnya'));

        // Seluruh pos bawaan memakai sumber yang ada keterangannya.
        foreach (AccountPosts::builtins() as $kode => $pos) {
            $this->assertArrayHasKey($pos['source'], AccountPosts::sources(), $kode);
        }
    }

    public function test_a_viewer_cannot_change_the_catalog(): void
    {
        $penonton = User::create([
            'name' => 'Penonton', 'email' => 'penonton@contoh.test', 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => Entity::where('code', 'HERBATECH')->value('id'),
        ]);
        $penonton->assignRole('Viewer');
        $this->actingAs($penonton);

        Livewire::test(RatioCatalog::class, ['year' => '2026'])
            ->call('editRatio', 'P1')
            ->set('formExpression', 'PA01 / PA02')
            ->call('saveRatio');

        $this->assertSame('LK / PA01 * 100', RatioDefinition::where('code', 'P1')->value('expression'));

        Livewire::test(AccountBalances::class, ['period' => '2026-08'])
            ->call('editPost', 'PA01')
            ->set('formName', 'Diubah penonton')
            ->call('savePost');

        AccountPosts::forget();
        $this->assertSame('Penjualan', AccountPosts::nameOf('PA01'));
    }
}
