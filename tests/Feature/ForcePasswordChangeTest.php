<?php

namespace Tests\Feature;

use App\Livewire\Auth\ChangePassword;
use App\Livewire\Auth\Login;
use App\Livewire\ManageUsers;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login:lemah@contoh.test|127.0.0.1');
        RateLimiter::clear('login:kuat@contoh.test|127.0.0.1');
    }

    private function buatPengguna(string $email, string $password): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::create([
            'name' => 'Pengguna Uji',
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $user->assignRole('Viewer');

        return $user;
    }

    private function login(string $email, string $password): Testable
    {
        return Livewire::test(Login::class)
            ->set('email', $email)
            ->set('password', $password)
            ->call('login');
    }

    /* ------------------------------------------------- deteksi saat login */

    public function test_a_weak_password_is_flagged_and_redirected_at_login(): void
    {
        $user = $this->buatPengguna('lemah@contoh.test', 'password');

        // Nilai bawaan kolom baru diketahui setelah dibaca ulang dari basis data.
        $this->assertFalse($user->fresh()->must_change_password);

        $this->login('lemah@contoh.test', 'password')
            ->assertRedirect(route('password.change'));

        $this->assertAuthenticated();
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_a_strong_password_is_not_flagged(): void
    {
        $user = $this->buatPengguna('kuat@contoh.test', 'Herbatech#2026aman');

        $this->login('kuat@contoh.test', 'Herbatech#2026aman')
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    /* ---------------------------------------------------------- pengunci */

    public function test_a_flagged_user_cannot_open_any_menu(): void
    {
        $user = $this->buatPengguna('lemah@contoh.test', 'password');
        $user->requirePasswordChange();

        foreach (['dashboard', 'financial-ratios', 'department-objectives', 'staging-logs'] as $rute) {
            $this->actingAs($user)
                ->get(route($rute))
                ->assertRedirect(route('password.change'));
        }
    }

    public function test_a_flagged_user_can_still_open_the_change_password_page(): void
    {
        $user = $this->buatPengguna('lemah@contoh.test', 'password');
        $user->requirePasswordChange();

        $this->actingAs($user)
            ->get(route('password.change'))
            ->assertOk()
            ->assertSee('wajib diganti', false);
    }

    public function test_a_flagged_user_can_still_log_out(): void
    {
        $user = $this->buatPengguna('lemah@contoh.test', 'password');
        $user->requirePasswordChange();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_user_without_the_flag_is_not_disturbed(): void
    {
        $user = $this->buatPengguna('kuat@contoh.test', 'Herbatech#2026aman');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    /* ------------------------------------------------------- ganti sandi */

    public function test_changing_the_password_clears_the_flag(): void
    {
        $user = $this->buatPengguna('lemah@contoh.test', 'password');
        $user->requirePasswordChange();
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'Herbatech#2026aman')
            ->set('password_confirmation', 'Herbatech#2026aman')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $segar = $user->fresh();
        $this->assertFalse($segar->must_change_password);
        $this->assertTrue(Hash::check('Herbatech#2026aman', $segar->password));
        $this->assertNotNull($segar->password_changed_at);
    }

    public function test_the_new_password_must_meet_the_policy(): void
    {
        $user = $this->buatPengguna('lemah@contoh.test', 'password');
        $user->requirePasswordChange();
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'password1')
            ->set('password_confirmation', 'password1')
            ->call('updatePassword')
            ->assertHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $user = $this->buatPengguna('lemah@contoh.test', 'password');
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->set('current_password', 'salah-total')
            ->set('password', 'Herbatech#2026aman')
            ->set('password_confirmation', 'Herbatech#2026aman')
            ->call('updatePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_confirmation_must_match(): void
    {
        $user = $this->buatPengguna('lemah@contoh.test', 'password');
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'Herbatech#2026aman')
            ->set('password_confirmation', 'Berbeda#2026aman')
            ->call('updatePassword')
            ->assertHasErrors('password');
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        $user = $this->buatPengguna('kuat@contoh.test', 'Herbatech#2026aman');
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->set('current_password', 'Herbatech#2026aman')
            ->set('password', 'Herbatech#2026aman')
            ->set('password_confirmation', 'Herbatech#2026aman')
            ->call('updatePassword')
            ->assertHasErrors('password');
    }

    /* -------------------------------------------------- opsi Super Admin */

    public function test_a_new_user_is_asked_to_change_the_password_by_default(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::where('email', 'superadmin@herbatech.co.id')->firstOrFail());

        Livewire::test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'Pengguna Baru')
            ->set('email', 'baru@contoh.test')
            ->set('role', 'Viewer')
            ->set('password', 'Herbatech#2026aman')
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertTrue(User::where('email', 'baru@contoh.test')->first()->must_change_password);
    }

    public function test_the_super_admin_can_turn_the_requirement_off(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::where('email', 'superadmin@herbatech.co.id')->firstOrFail());

        Livewire::test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'Pengguna Baru')
            ->set('email', 'baru@contoh.test')
            ->set('role', 'Viewer')
            ->set('password', 'Herbatech#2026aman')
            ->set('must_change_password', false)
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertFalse(User::where('email', 'baru@contoh.test')->first()->must_change_password);
    }
}
