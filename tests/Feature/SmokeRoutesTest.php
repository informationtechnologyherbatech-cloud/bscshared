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
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_every_menu_renders_for_a_super_admin(string $routeName): void
    {
        $response = $this->actingAs($this->superAdmin())->get(route($routeName));

        $response->assertOk();
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
