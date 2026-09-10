<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_an_inactive_user_is_logged_out_by_the_active_middleware(): void
    {
        $user = $this->superAdmin();
        $user->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }
}
