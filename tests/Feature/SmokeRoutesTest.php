<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke test seluruh menu: memastikan setiap halaman masih ter-render
 * setelah aplikasi dinaikkan ke Laravel 13 / PHP 8.4.
 */
class SmokeRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return User::where('email', 'superadmin@herbatech.co.id')->firstOrFail();
    }

    public static function routeProvider(): array
    {
        return [
            'piramida' => ['dashboard'],
            'rasio' => ['financial-ratios'],
            'objective' => ['department-objectives'],
            'wiring' => ['bsc-wiring'],
            'dampak' => ['dampak'],
            'coa' => ['coa'],
            'action plan' => ['action-plans'],
            'ibp' => ['ibp'],
            'sensitivitas' => ['sensitivity'],
            'skenario' => ['skenario'],
            'dokumentasi' => ['dokumentasi'],
            'integrasi' => ['system-integration'],
            'staging log' => ['staging-logs'],
            'manajemen pengguna' => ['manage-users'],
            'pengaturan' => ['settings'],
            'target revenue' => ['revenue'],
            'unit kerja' => ['work-units'],
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_every_menu_renders_for_a_super_admin(string $routeName): void
    {
        $response = $this->actingAs($this->superAdmin())->get(route($routeName));

        $response->assertOk();
    }

    /**
     * Halaman yang boleh dibuka tiap peran non-Super-Admin. Menjaga agar blok
     * izin yang menyembunyikan tombol tidak merusak tampilan bagi peran itu.
     *
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function roleProvider(): array
    {
        return [
            'Admin FAT' => ['Admin FAT', [
                'dashboard', 'financial-ratios', 'department-objectives', 'bsc-wiring',
                'action-plans', 'system-integration', 'staging-logs', 'settings',
            ]],
            'Admin HRIS' => ['Admin HRIS', [
                'dashboard', 'department-objectives', 'bsc-wiring', 'action-plans',
                'system-integration', 'staging-logs',
            ]],
            'Kepala Departemen' => ['Kepala Departemen', [
                'dashboard', 'financial-ratios', 'department-objectives', 'bsc-wiring',
                'action-plans', 'staging-logs',
            ]],
            'Operator' => ['Operator', [
                'dashboard', 'department-objectives', 'bsc-wiring', 'action-plans',
            ]],
            'Viewer' => ['Viewer', [
                'dashboard', 'financial-ratios', 'department-objectives', 'bsc-wiring',
                'staging-logs',
            ]],
        ];
    }

    /**
     * @param  array<int, string>  $routeNames
     */
    #[DataProvider('roleProvider')]
    public function test_each_role_can_open_its_own_menus(string $role, array $routeNames): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Pengguna '.$role,
            'email' => str($role)->slug().'@contoh.test',
            'password' => bcrypt('rahasia123'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        foreach ($routeNames as $routeName) {
            $this->actingAs($user)
                ->get(route($routeName))
                ->assertOk("Peran {$role} seharusnya dapat membuka rute {$routeName}.");
        }
    }

    public function test_a_viewer_is_blocked_from_pages_outside_its_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $viewer = User::create([
            'name' => 'Pengguna Viewer',
            'email' => 'viewer-terbatas@contoh.test',
            'password' => bcrypt('rahasia123'),
            'is_active' => true,
        ]);
        $viewer->assignRole('Viewer');

        foreach (['manage-users', 'settings'] as $routeName) {
            $this->actingAs($viewer)
                ->get(route($routeName))
                ->assertForbidden();
        }

        // Viewer memegang "view gateway", jadi halaman integrasi memang boleh
        // dibuka — tetapi hanya untuk dipantau, tanpa tombol yang menulis data.
        $this->actingAs($viewer)
            ->get(route('system-integration'))
            ->assertOk()
            ->assertDontSee('Terima &amp; Sinkronkan Data Finance ERP', false)
            ->assertDontSee('Uji Coba Kirim API Payload Inbound', false);

        $this->actingAs($viewer)
            ->get(route('staging-logs'))
            ->assertOk()
            ->assertDontSee('Kirim Payload Simulasi', false);
    }

    public function test_a_viewer_does_not_see_period_controls_on_the_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $viewer = User::create([
            'name' => 'Pengguna Viewer',
            'email' => 'viewer-dashboard@contoh.test',
            'password' => bcrypt('rahasia123'),
            'is_active' => true,
        ]);
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Periode Baru', false)
            ->assertDontSee('Kunci Periode', false);
    }

    public function test_the_layout_does_not_repeat_identity_in_two_places(): void
    {
        $response = $this->actingAs($this->superAdmin())->get(route('dashboard'));

        // Panel pengguna sidebar dihapus: nama & peran sudah ada di navbar.
        $response->assertDontSee('class="user-panel', false);
        // Nama aplikasi cukup pada brand sidebar, tidak diulang di navbar kiri.
        $response->assertDontSee('nav-item d-none d-sm-inline-block', false);
    }

    public function test_the_navbar_still_shows_the_role_and_department(): void
    {
        $user = $this->superAdmin();
        $user->update(['dept_code' => 'FAT']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            // Departemen dulu hanya tampil di panel sidebar yang kini dihapus.
            ->assertSee('Super Admin · FAT', false);
    }

    public function test_the_sidebar_shows_the_uploaded_entity_logo(): void
    {
        $user = $this->superAdmin();
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', 'x');
        AppSetting::setValue('app_logo', 'branding/logo.png');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(asset('storage/branding/logo.png'), false);
    }

    public function test_an_inactive_user_is_logged_out_by_the_active_middleware(): void
    {
        $user = $this->superAdmin();
        $user->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }
}
