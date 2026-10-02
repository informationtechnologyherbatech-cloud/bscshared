<?php

namespace Tests\Feature;

use App\Livewire\BscDashboard;
use App\Models\AccountBalance;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\User;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dari mana angka kolom Actual berasal.
 *
 * Pertanyaan yang paling sering muncul saat melihat tabel rasio adalah "angka
 * ini dari mana?" — kolom Actual tidak diketik siapa pun, melainkan dihitung
 * dari Pos Akun. Yang dijaga di sini: penjelasannya ada di layar, dan Telusur
 * benar-benar menunjukkan rumus beserta pos pembentuknya.
 */
class AsalAngkaRasioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(EntityContext::class)->use((int) Entity::where('code', 'HERBATECH')->value('id'));
        Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);
    }

    /** Tombol perbaikan hanya tampil bagi yang berhak mengubahnya. */
    private function masukSebagaiAdmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::where('email', 'superadmin@emc.co.id')->firstOrFail());
        app(EntityContext::class)->use((int) Entity::where('code', 'HERBATECH')->value('id'));
    }

    private function isiPosAkun(): void
    {
        // Penjualan & HPP cukup untuk melahirkan Gross Profit Margin.
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA01', 'amount' => 800_000_000]);
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA02', 'amount' => 500_000_000]);

        app(RatioEngine::class)->materialize('2026-08');
    }

    public function test_every_column_explains_where_its_number_comes_from(): void
    {
        foreach (['kategori', 'nama', 'target', 'actual', 'capaian', 'status', 'telusur'] as $kolom) {
            $info = ratio_column_help($kolom);

            $this->assertNotSame('', $info['judul'], 'Kolom '.$kolom.' belum punya judul.');
            $this->assertNotSame('', $info['ringkas'], 'Kolom '.$kolom.' belum punya keterangan.');
        }

        // Dua kolom yang paling sering disalahpahami harus menyebutkan asalnya
        // dengan tegas: target diketik manusia, actual dihitung aplikasi.
        $this->assertStringContainsString('DIISI MANUSIA', ratio_column_help('target')['ringkas']);
        $this->assertStringContainsString('DIHITUNG APLIKASI', ratio_column_help('actual')['ringkas']);
        $this->assertStringContainsString('Pos Akun', ratio_column_help('actual')['ringkas']);
    }

    public function test_the_ratio_table_carries_the_help_icons(): void
    {
        $this->isiPosAkun();

        Livewire::test(BscDashboard::class)
            ->set('activeLevel', 2)
            ->assertSee('col-info')
            ->assertSee('DIHITUNG APLIKASI dari Pos Akun', false);
    }

    public function test_telusur_shows_the_formula_and_the_posts_behind_the_number(): void
    {
        $this->isiPosAkun();

        $gpm = FinancialRatio::where('period', '2026-08')->where('ratio_code', 'P1')->sole();

        $halaman = Livewire::test(BscDashboard::class)->call('inspectItem', 'ratio', $gpm->id);
        $rincian = $halaman->get('selectedItemDetail');

        // Rumusnya, bukan sekadar pengulangan angka yang sudah terlihat di tabel.
        $this->assertSame('Laba kotor ÷ Penjualan × 100', $rincian['formula']);

        $pos = collect($rincian['pembentuk'])->keyBy('code');
        $this->assertTrue($pos->has('PA01'), 'Penjualan harus disebut sebagai pembentuk GPM.');
        $this->assertTrue($pos->has('PA02'), 'HPP harus disebut sebagai pembentuk GPM.');
        // Hanya pos yang benar-benar masuk perhitungan yang disebut — GPM tidak
        // memakai beban usaha, jadi PA03 tidak boleh ikut tercantum.
        $this->assertFalse($pos->has('PA03'));

        // Nilai yang DIPAKAI, bukan nilai mentah: Agustus = bulan ke-8, jadi
        // pos aliran disetahunkan ×12 ÷ 8.
        $this->assertSame(1_200_000_000.0, (float) $pos['PA01']['value']);
        // Isian apa adanya DAN aritmetika yang mengubahnya jadi nilai dipakai —
        // tanpa ini, "402.685.218.455 asalnya dari mana?" tetap tak terjawab.
        $this->assertSame(800_000_000.0, (float) $pos['PA01']['amount']);
        $this->assertSame('YTD × 12 ÷ 8', $pos['PA01']['label']);
        $this->assertStringContainsString('800.000.000 × 12 ÷ 8 bulan', $pos['PA01']['arithmetic']);
        $this->assertStringContainsString('×12 ÷ 8 bulan berjalan', $rincian['catatan']);

        $halaman->assertSee('Dari mana angka Actual ini')
            ->assertSee('Laba kotor ÷ Penjualan × 100')
            // Rincian asal angka tertutup secara bawaan — yang dicari kebanyakan
            // orang adalah langkah hitungnya, bukan isian mentahnya.
            ->assertSee('Angka-angka itu asalnya dari mana')
            ->assertDontSee('<details class="asal-akordeon mt-3" open', false);
    }

    public function test_the_derivation_always_matches_the_value_the_engine_uses(): void
    {
        // Penjaga kedua: keterangan "isian → nilai dipakai" adalah tiruan dari
        // usedValues(). Keduanya harus sama untuk seluruh 16 pos, apa pun
        // jenisnya dan ada-tidaknya saldo awal.
        $isian = [];

        foreach (array_keys(AccountPosts::all()) as $kode) {
            $isian[$kode] = ['amount' => 1_000_000.0, 'opening' => 400_000.0];
        }
        // Satu pos neraca sengaja tanpa saldo awal, satu pos sengaja kosong.
        $isian['PA08']['opening'] = null;
        $isian['PA14'] = ['amount' => null, 'opening' => null];

        $dipakai = AccountPosts::usedValues($isian, 8);

        foreach (array_keys(AccountPosts::all()) as $kode) {
            $asal = AccountPosts::derivation($kode, $isian[$kode]['amount'], $isian[$kode]['opening'], 8);

            $this->assertSame(
                $dipakai[$kode],
                $asal['value'],
                'Keterangan asal '.$kode.' tidak sama dengan nilai yang dipakai mesin rasio.'
            );
        }

        // Dan bunyinya memang menjelaskan, bukan sekadar mengulang angka.
        $this->assertSame('YTD × 12 ÷ 8', AccountPosts::derivation('PA01', 1_000_000.0, null, 8)['label']);
        $this->assertSame('rata-rata awal & akhir', AccountPosts::derivation('PA06', 1_000_000.0, 400_000.0, 8)['label']);
        $this->assertSame('saldo akhir', AccountPosts::derivation('PA08', 1_000_000.0, null, 8)['label']);
        $this->assertSame('belum diisi', AccountPosts::derivation('PA14', null, null, 8)['label']);
    }

    public function test_the_steps_always_end_at_the_number_the_engine_computed(): void
    {
        // Penjaga terpenting berkas ini: langkah yang ditampilkan ke pengguna
        // adalah tiruan dari compute(). Bila kelak salah satunya diubah
        // sendirian, pengujian ini berbunyi — bukan penggunanya yang menemukan.
        $p = AccountPosts::withDerived([
            'PA01' => 402_685_218_455.0, 'PA02' => 174_814_102_172.0, 'PA03' => 184_259_278_579.0,
            'PA04' => 20_000_000_000.0, 'PA05' => 30_000_000_000.0, 'PA06' => 25_000_000_000.0,
            'PA07' => 18_000_000_000.0, 'PA08' => 12_000_000_000.0, 'PA09' => 70_000_000_000.0,
            'PA10' => 40_000_000_000.0, 'PA11' => 120_000_000_000.0, 'PA12' => 60_000_000_000.0,
            'PA13' => -12_490_293_623.0, 'PA14' => 50_000_000_000.0,
            'PA15' => 320.0, 'PA16' => 480_000.0,
        ]);

        foreach (array_keys(RatioLibrary::all()) as $kode) {
            $langkah = RatioLibrary::steps($kode, $p);
            $terakhir = end($langkah['steps']);

            $this->assertNotFalse($terakhir, 'Rasio '.$kode.' belum punya langkah perhitungan.');
            $this->assertEqualsWithDelta(
                (float) RatioLibrary::compute($kode, $p),
                (float) $terakhir['nilai'],
                0.000001,
                'Langkah terakhir '.$kode.' tidak sama dengan hasil compute().'
            );
        }
    }

    public function test_the_steps_reproduce_a_negative_return_on_equity(): void
    {
        // Angka dari layar pengguna: laba positif, ekuitas negatif → −349,17%.
        $p = AccountPosts::withDerived([
            'PA01' => 402_685_218_455.0,
            'PA02' => 174_814_102_172.0,
            'PA03' => 184_259_278_579.0,
            'PA13' => -12_490_293_623.0,
        ]);

        $langkah = RatioLibrary::steps('P4', $p)['steps'];

        $this->assertCount(3, $langkah);
        $this->assertSame('Laba kotor', $langkah[0]['label']);
        $this->assertSame(227_871_116_283.0, $langkah[0]['nilai']);
        $this->assertSame('Laba bersih', $langkah[1]['label']);
        $this->assertSame(43_611_837_704.0, $langkah[1]['nilai']);
        $this->assertSame('Return on Equity', $langkah[2]['label']);
        $this->assertEqualsWithDelta(-349.17, $langkah[2]['nilai'], 0.01);

        // Angkanya ditulis apa adanya supaya dapat dibaca tanpa kalkulator.
        $this->assertStringContainsString('402.685.218.455', $langkah[0]['angka']);
        $this->assertStringContainsString('-12.490.293.623', $langkah[2]['angka']);
    }

    public function test_a_negative_denominator_is_explained_not_left_a_mystery(): void
    {
        $this->masukSebagaiAdmin();
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA01', 'amount' => 800_000_000]);
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA02', 'amount' => 500_000_000]);
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA03', 'amount' => 100_000_000]);
        AccountBalance::create(['period' => '2026-08', 'code' => 'PA13', 'amount' => -50_000_000]);
        app(RatioEngine::class)->materialize('2026-08');

        $roe = FinancialRatio::where('period', '2026-08')->where('ratio_code', 'P4')->sole();

        $halaman = Livewire::test(BscDashboard::class)->call('inspectItem', 'ratio', $roe->id);
        $rincian = $halaman->get('selectedItemDetail');

        $this->assertStringContainsString('bernilai NEGATIF', $rincian['peringatan']['teks']);
        $this->assertStringContainsString('balik tanda', $rincian['peringatan']['teks']);
        // Pos yang bermasalah dibawa terpisah supaya tombolnya dapat menunjuk ke sana.
        $this->assertSame('PA13', $rincian['peringatan']['pos']);

        $halaman->assertSee('Laba kotor')
            ->assertSee('Laba bersih')
            // Tombol menuju tempat perbaikannya, bukan hanya menyebut nama menunya.
            ->assertSee('Perbaiki pos PA13')
            ->assertSee('#pos-PA13', false)
            ->assertSee('Buka Pemetaan Akun');
    }

    public function test_a_post_that_is_still_empty_says_so_instead_of_showing_zero(): void
    {
        $this->isiPosAkun();

        // Return on Equity memakai PA13 Ekuitas, yang sengaja belum diisi.
        $roe = FinancialRatio::where('period', '2026-08')->where('ratio_code', 'P4')->first();

        if (! $roe) {
            $this->markTestSkipped('P4 tidak terhitung tanpa ekuitas — tidak ada barisnya untuk ditelusur.');
        }

        $rincian = Livewire::test(BscDashboard::class)
            ->call('inspectItem', 'ratio', $roe->id)->get('selectedItemDetail');

        $ekuitas = collect($rincian['pembentuk'])->firstWhere('code', 'PA13');
        $this->assertNull($ekuitas['value']);
    }
}
