<?php

namespace Tests\Feature;

use App\Livewire\ActionPlans;
use App\Livewire\AppSettings;
use App\Livewire\BscDashboard;
use App\Livewire\DepartmentObjectives;
use App\Livewire\StagingLogs;
use App\Livewire\SystemIntegration;
use App\Livewire\WorkUnits;
use App\Models\ActionPlan;
use App\Models\AppSetting;
use App\Models\DepartmentObjective;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\StagingLog;
use App\Models\User;
use App\Models\WorkUnit;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Middleware rute hanya menjaga AKSES HALAMAN. Aksi Livewire dapat dipanggil
 * siapa pun yang berhasil membuka halamannya, sehingga setiap aksi yang menulis
 * data harus memeriksa izinnya sendiri.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Pengguna '.$role,
            'email' => str($role)->slug().'@contoh.test',
            'password' => bcrypt('rahasia123'),
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }

    /* ------------------------------------------------- periode (dashboard) */

    public function test_a_viewer_cannot_close_a_period_from_the_dashboard(): void
    {
        $this->actingAs($this->userWithRole('Viewer'));
        $period = Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);

        Livewire::test(BscDashboard::class)->call('togglePeriodStatus');

        $this->assertSame('OPEN', $period->fresh()->status);
    }

    public function test_a_viewer_cannot_create_a_period(): void
    {
        $this->actingAs($this->userWithRole('Viewer'));

        Livewire::test(BscDashboard::class)
            ->set('newPeriodInput', '2027-01')
            ->call('createNewPeriod');

        $this->assertDatabaseMissing('periods', ['period' => '2027-01']);
    }

    /**
     * Periode hanya dapat dikunci setelah bulannya berakhir.
     *
     * Mengunci bulan berjalan — apalagi bulan yang belum tiba — berarti menutup
     * buku atas angka yang belum selesai dikumpulkan, dan seluruh menu bulanan
     * langsung ikut terkunci.
     */
    public function test_only_a_month_that_has_ended_can_be_locked(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));

        $berjalan = now()->format('Y-m');
        $mendatang = now()->addMonth()->format('Y-m');
        $lewat = now()->subMonth()->format('Y-m');

        foreach ([$berjalan, $mendatang, $lewat] as $p) {
            Period::create(['period' => $p, 'status' => 'OPEN', 'apex_score' => 0]);
        }

        // Bulan berjalan dan bulan mendatang: tombolnya tidak ditawarkan …
        foreach ([$berjalan, $mendatang] as $p) {
            $uji = Livewire::test(BscDashboard::class)->set('selectedPeriod', $p);

            $uji->assertDontSee('Kunci Periode')->assertSee('Belum berakhir');

            // … dan tetap ditolak bila aksinya dipanggil langsung.
            $uji->call('togglePeriodStatus');
            $this->assertSame('OPEN', Period::where('period', $p)->value('status'), $p.' seharusnya tetap terbuka');
        }

        // Bulan yang sudah lewat: boleh dikunci, dan boleh dibuka kembali.
        $uji = Livewire::test(BscDashboard::class)->set('selectedPeriod', $lewat);
        $uji->assertSee('Kunci Periode')->call('togglePeriodStatus');
        $this->assertSame('CLOSED', Period::where('period', $lewat)->value('status'));

        $uji->call('togglePeriodStatus');
        $this->assertSame('OPEN', Period::where('period', $lewat)->value('status'));
    }

    public function test_a_closed_period_can_always_be_reopened_even_if_the_month_is_running(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));

        $berjalan = now()->format('Y-m');
        Period::create(['period' => $berjalan, 'status' => 'CLOSED', 'apex_score' => 0]);

        Livewire::test(BscDashboard::class)
            ->set('selectedPeriod', $berjalan)
            ->assertSee('Buka Periode')
            ->call('togglePeriodStatus');

        $this->assertSame('OPEN', Period::where('period', $berjalan)->value('status'));
    }

    public function test_an_admin_fat_can_still_manage_periods(): void
    {
        $this->actingAs($this->userWithRole('Admin FAT'));
        $period = Period::create(['period' => '2026-08', 'status' => 'OPEN', 'apex_score' => 0]);

        Livewire::test(BscDashboard::class)->call('togglePeriodStatus');

        $this->assertSame('CLOSED', $period->fresh()->status);
    }

    /* --------------------------------------------------------- integrasi */

    /**
     * Pembaca tidak boleh DITAWARI tombol tulis.
     *
     * Formulir yang tampil lalu ditolak diam-diam saat disimpan adalah jebakan:
     * pengguna sudah mengetik, baru tahu tidak boleh. Semua halaman tulis
     * menyembunyikan kontrolnya; Program Kerja dulu tidak.
     */
    public function test_a_viewer_is_not_offered_write_controls(): void
    {
        $this->actingAs($this->userWithRole('Viewer'));

        $halaman = [
            ActionPlans::class => ['Simpan Program Kerja', 'Update Progres'],
            DepartmentObjectives::class => ['Simpan'],
            WorkUnits::class => ['Tambah unit'],
        ];

        foreach ($halaman as $komponen => $tombol) {
            $uji = Livewire::test($komponen);

            foreach ($tombol as $teks) {
                $uji->assertDontSee($teks);
            }
        }

        // Keterangannya jelas, bukan halaman yang diam-diam setengah kosong.
        Livewire::test(ActionPlans::class)->assertSee('sebagai pembaca');
    }

    public function test_a_viewer_cannot_create_an_action_plan_even_by_calling_it_directly(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->userWithRole('Viewer'));

        Livewire::test(ActionPlans::class)
            ->set('title', 'Program selundupan')
            ->set('ownerDept', WorkUnit::query()->value('code') ?? 'PROD')
            ->call('createPlan');

        $this->assertDatabaseMissing('action_plans', ['title' => 'Program selundupan']);
    }

    /**
     * Progres program kerja diubah lewat modal, bukan disisipkan ke dalam sel tabel.
     *
     * Dilaporkan QC: slider beserta tombol ✓/✗ dirender langsung di dalam sel,
     * sehingga lebar kolom melar dan tata letak tabel rusak; angka persennya pun
     * tetap 0% selagi digeser, sehingga terkesan tidak tersimpan.
     */
    public function test_progress_is_edited_in_a_dialog_not_inside_the_table_cell(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->userWithRole('Super Admin'));

        $sasaran = DepartmentObjective::query()->firstOrFail();
        $rencana = ActionPlan::create([
            'department_objective_id' => $sasaran->id,
            'title' => 'Program yang diuji',
            'owner_dept' => $sasaran->dept_code,
            'progress_pct' => 20,
            'status' => 'On Progress',
        ]);

        $uji = Livewire::test(ActionPlans::class, ['selectedPeriod' => $rencana->period])
            ->call('editProgressModal', $rencana->id);

        // Penyuntingnya berupa dialog, dengan judul program kerjanya.
        $uji->assertSee('Perbarui progres')
            ->assertSee('Program yang diuji')
            ->assertSeeHtml('modal-lw');

        // Angkanya terikat hidup, sehingga ikut bergerak selagi digeser.
        $uji->assertSeeHtml('wire:model.live="editProgress"')
            ->assertDontSeeHtml('wire:model="editProgress"');

        // Dan nilainya benar-benar tersimpan beserta statusnya.
        $uji->set('editProgress', 100)->call('updateProgress');

        $this->assertSame(100, (int) $rencana->fresh()->progress_pct);
        $this->assertSame('Completed', $rencana->fresh()->status);
    }

    /**
     * Program kerja baru mendarat di periode yang sedang DILIHAT.
     *
     * Daftar di halaman ini tersaring per periode, sedangkan periode di bilah
     * atas bisa berbeda. Memakai periode bilah atas membuat program kerja yang
     * baru dibuat langsung hilang dari daftar di depan mata pembuatnya.
     */
    public function test_a_new_action_plan_lands_in_the_period_being_viewed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->userWithRole('Super Admin'));

        Period::firstOrCreate(['period' => '2026-09'], ['status' => 'OPEN', 'apex_score' => 0]);
        $unit = WorkUnit::query()->value('code');

        // Bilah atas pada periode terbaru, halaman menampilkan periode lebih lama.
        $this->assertSame('2026-09', Period::currentPeriod());

        Livewire::test(ActionPlans::class, ['selectedPeriod' => '2026-08'])
            ->set('title', 'Program periode Agustus')
            ->set('ownerDept', $unit)
            ->call('createPlan')
            ->assertHasNoErrors()
            // Dan langsung terlihat di daftar yang sedang dibuka.
            ->assertSee('Program periode Agustus');

        $this->assertSame('2026-08', ActionPlan::where('title', 'Program periode Agustus')->value('period'));

        // "Semua periode" tidak menunjuk satu periode, jadi dipakai periode aktif.
        Livewire::test(ActionPlans::class, ['selectedPeriod' => 'semua'])
            ->set('title', 'Program tanpa periode terpilih')
            ->set('ownerDept', $unit)
            ->call('createPlan')
            ->assertHasNoErrors();

        $this->assertSame('2026-09', ActionPlan::where('title', 'Program tanpa periode terpilih')->value('period'));
    }

    public function test_the_kpi_list_in_the_form_follows_the_period_being_viewed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->userWithRole('Super Admin'));

        Period::firstOrCreate(['period' => '2026-09'], ['status' => 'OPEN', 'apex_score' => 0]);

        $sasaranAgustus = DepartmentObjective::where('period', '2026-08')->first();
        $this->assertNotNull($sasaranAgustus, 'data contoh harus punya sasaran Agustus');

        // Menampilkan Agustus → sasaran Agustus yang ditawarkan, bukan September.
        Livewire::test(ActionPlans::class, ['selectedPeriod' => '2026-08'])
            ->assertViewHas('offTargetObjectives', fn ($daftar) => $daftar->every(fn ($o) => $o->period === '2026-08'));

        Livewire::test(ActionPlans::class, ['selectedPeriod' => '2026-09'])
            ->assertViewHas('offTargetObjectives', fn ($daftar) => $daftar->every(fn ($o) => $o->period === '2026-09'));
    }

    /**
     * Periode yang sudah ditutup juga mengunci Program Kerja.
     *
     * Pos Akun dan Target & Realisasi sudah menolak perubahan pada periode
     * CLOSED; program kerja dulu satu-satunya menu bulanan yang masih bisa
     * ditulisi setelah buku ditutup.
     */
    public function test_a_closed_period_locks_action_plans_too(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->userWithRole('Super Admin'));

        Period::where('period', '2026-08')->update(['status' => 'CLOSED']);
        $unit = WorkUnit::query()->value('code');

        $lama = ActionPlan::create([
            'period' => '2026-08', 'title' => 'Program lama', 'owner_dept' => $unit,
            'progress_pct' => 30, 'status' => 'On Progress',
        ]);

        $uji = Livewire::test(ActionPlans::class, ['selectedPeriod' => '2026-08']);

        // Menambah ditolak …
        $uji->set('title', 'Program selundupan')->set('ownerDept', $unit)->call('createPlan');
        $this->assertDatabaseMissing('action_plans', ['title' => 'Program selundupan']);

        // … dan mengubah progres juga ditolak.
        $uji->call('editProgressModal', $lama->id)->set('editProgress', 90)->call('updateProgress');
        $this->assertSame(30, (int) $lama->fresh()->progress_pct);

        // Layarnya mengatakannya, bukan menolak diam-diam.
        $uji->assertSee('sudah ditutup')
            ->assertDontSee('Simpan Program Kerja')
            ->assertDontSee('Update Progres');

        // Periode lain tetap dapat diisi seperti biasa.
        Period::firstOrCreate(['period' => '2026-09'], ['status' => 'OPEN', 'apex_score' => 0]);
        Livewire::test(ActionPlans::class, ['selectedPeriod' => '2026-09'])
            ->set('title', 'Program periode terbuka')->set('ownerDept', $unit)->call('createPlan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('action_plans', ['title' => 'Program periode terbuka', 'period' => '2026-09']);
    }

    public function test_the_audit_log_page_never_writes_anything(): void
    {
        $this->actingAs($this->userWithRole('Viewer'));

        // Halaman ini sengaja tidak punya satu pun tindakan yang menulis: jejak
        // audit yang isinya dapat dikarang dari layarnya sendiri tidak lagi
        // menjadi bukti. Dahulu ada tombol "Kirim Payload Simulasi" yang menulis
        // baris berbunyi "diterima dan berhasil dihitung" padahal tidak ada satu
        // angka pun yang berubah.
        Livewire::test(StagingLogs::class)
            ->set('cari', 'apa saja')
            ->set('status', 'ERROR')
            ->call('bersihkanSaringan');

        $this->assertSame(0, StagingLog::count());
        $this->assertFalse(method_exists(StagingLogs::class, 'simulateInbound'));
    }

    public function test_a_viewer_cannot_overwrite_financial_ratios_through_the_gateway(): void
    {
        $this->actingAs($this->userWithRole('Viewer'));

        Livewire::test(SystemIntegration::class)->call('processFinancePayload');

        $this->assertSame(0, FinancialRatio::count());
    }

    public function test_an_admin_fat_can_still_use_the_gateway(): void
    {
        $this->actingAs($this->userWithRole('Admin FAT'));

        // Isian formulir diisi di sini, bukan mengandalkan nilai bawaan: sejak
        // angka contoh dibuang, formulirnya berangkat dari pos akun yang
        // tersimpan — dan pada awal uji memang masih kosong.
        Livewire::test(SystemIntegration::class)
            ->set('salesPayload', 96000)
            ->set('hppPayload', 57600)
            ->set('opexPayload', 23400)
            ->set('kasPayload', 12500)
            ->set('piutangPayload', 9800)
            ->set('persediaanPayload', 14200)
            ->set('hutangPayload', 8200)
            ->set('modalPayload', 73300)
            ->call('processFinancePayload');

        $this->assertGreaterThan(0, FinancialRatio::count());
    }

    /* --------------------------------------------------------- pengaturan */

    public function test_an_admin_fat_cannot_rebrand_the_application(): void
    {
        // Admin FAT boleh membuka Setting Sistem karena memegang "manage apikey",
        // tetapi tidak memegang "manage settings".
        $this->actingAs($this->userWithRole('Admin FAT'));

        Livewire::test(AppSettings::class)
            ->set('entity_name', 'Entitas Bajakan')
            ->set('company_name', 'PT Bajakan')
            ->call('saveEntity');

        $this->assertNotSame('PT Bajakan', AppSetting::getValue('company_name'));
    }

    public function test_an_admin_fat_cannot_switch_recaptcha_on_or_off(): void
    {
        $this->actingAs($this->userWithRole('Admin FAT'));

        Livewire::test(AppSettings::class)
            ->set('recaptcha_enabled', true)
            ->set('recaptcha_site_key', 'site')
            ->set('recaptcha_secret_key', 'secret')
            ->call('saveSecurity');

        $this->assertSame('0', (string) AppSetting::getValue('recaptcha_enabled', '0'));
    }

    public function test_an_admin_fat_can_still_manage_api_keys(): void
    {
        $this->actingAs($this->userWithRole('Admin FAT'));

        Livewire::test(AppSettings::class)
            ->set('newKeyName', 'Kunci Gateway Baru')
            ->call('generateApiKey');

        $this->assertDatabaseHas('api_keys', ['name' => 'Kunci Gateway Baru']);
    }

    public function test_a_super_admin_can_do_everything_on_the_settings_page(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));

        Livewire::test(AppSettings::class)
            ->set('entity_name', 'Entitas Sah')
            ->set('company_name', 'PT Entitas Sah')
            ->call('saveEntity')
            ->assertHasNoErrors();

        $this->assertSame('PT Entitas Sah', AppSetting::getValue('company_name'));
    }
}
