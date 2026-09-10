<?php

namespace Tests\Feature;

use App\Livewire\ManageUsers;
use App\Models\User;
use App\Support\PasswordPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs(
            User::where('email', 'superadmin@herbatech.co.id')->firstOrFail()
        );
    }

    private function isiFormulir(string $password): Testable
    {
        return Livewire::test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'Pengguna Baru')
            ->set('email', 'baru@contoh.test')
            ->set('role', 'Viewer')
            ->set('password', $password);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function weakPasswordProvider(): array
    {
        return [
            'terlalu pendek' => ['Ab1@ef'],
            'tanpa huruf besar' => ['rahasia123!'],
            'tanpa huruf kecil' => ['RAHASIA123!'],
            'tanpa angka' => ['RahasiaKuat!'],
            'tanpa karakter khusus' => ['RahasiaKuat123'],
            'hanya angka' => ['1234567890'],
            'kata umum' => ['password'],
        ];
    }

    #[DataProvider('weakPasswordProvider')]
    public function test_a_weak_password_is_rejected_when_creating_a_user(string $password): void
    {
        $this->actingAsSuperAdmin();

        $this->isiFormulir($password)
            ->call('saveUser')
            ->assertHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'baru@contoh.test']);
    }

    public function test_a_strong_password_is_accepted(): void
    {
        $this->actingAsSuperAdmin();

        $this->isiFormulir('Herbatech#2026aman')
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'baru@contoh.test']);
    }

    public function test_editing_a_user_without_touching_the_password_keeps_the_old_one(): void
    {
        $this->actingAsSuperAdmin();

        $user = User::create([
            'name' => 'Pengguna Lama',
            'email' => 'lama@contoh.test',
            'password' => Hash::make('KataSandiLama#1'),
            'is_active' => true,
        ]);
        $user->assignRole('Viewer');

        Livewire::test(ManageUsers::class)
            ->call('openEdit', $user->id)
            ->set('name', 'Pengguna Lama Diperbarui')
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('KataSandiLama#1', $user->fresh()->password));
        $this->assertSame('Pengguna Lama Diperbarui', $user->fresh()->name);
    }

    public function test_a_weak_password_is_also_rejected_when_editing(): void
    {
        $this->actingAsSuperAdmin();

        $user = User::create([
            'name' => 'Pengguna Lama',
            'email' => 'lama@contoh.test',
            'password' => Hash::make('KataSandiLama#1'),
            'is_active' => true,
        ]);
        $user->assignRole('Viewer');

        Livewire::test(ManageUsers::class)
            ->call('openEdit', $user->id)
            ->set('password', '12345678')
            ->call('saveUser')
            ->assertHasErrors('password');

        $this->assertTrue(Hash::check('KataSandiLama#1', $user->fresh()->password));
    }

    public function test_the_policy_is_configurable(): void
    {
        config()->set('security.password.symbols', false);
        config()->set('security.password.min_length', 8);

        $lolos = Validator::make(
            ['password' => 'TanpaSimbol9'],
            ['password' => [PasswordPolicy::rule()]]
        );

        $this->assertTrue($lolos->passes());
        $this->assertSame(8, PasswordPolicy::minLength());
    }

    public function test_the_minimum_length_never_drops_below_eight(): void
    {
        config()->set('security.password.min_length', 4);

        $this->assertSame(8, PasswordPolicy::minLength());
    }

    public function test_validation_messages_are_in_indonesian(): void
    {
        $pesan = Validator::make(
            ['password' => 'abc'],
            ['password' => [PasswordPolicy::rule()]]
        )->errors()->all();

        $gabungan = implode(' ', $pesan);

        $this->assertStringContainsString('minimal', $gabungan);
        $this->assertStringContainsString('huruf besar', $gabungan);
        $this->assertStringContainsString('angka', $gabungan);
        $this->assertStringContainsString('karakter khusus', $gabungan);
    }

    /* ------------------------------------------------- tampil/sembunyi sandi */

    public function test_the_login_page_offers_a_show_hide_password_toggle(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('show: false', false)
            ->assertSee("show ? 'text' : 'password'", false)
            ->assertSee('Sembunyikan password', false);
    }

    public function test_the_user_form_offers_a_show_hide_password_toggle_and_a_checklist(): void
    {
        $this->actingAsSuperAdmin();

        // Formulir berada di dalam modal, jadi harus dibuka lebih dulu.
        Livewire::test(ManageUsers::class)
            ->call('openCreate')
            ->assertSee("show ? 'text' : 'password'", false)
            ->assertSee('Sembunyikan password', false)
            // Daftar syarat ikut ter-render.
            ->assertSee('Ada huruf besar (A-Z)', false)
            ->assertSee('Ada angka (0-9)', false)
            ->assertSee('Ada karakter khusus', false);
    }

    public function test_the_checklist_matches_the_rules_that_are_actually_enforced(): void
    {
        $labels = array_column(PasswordPolicy::checklist(), 'label');

        $this->assertContains('Minimal '.PasswordPolicy::minLength().' karakter', $labels);

        config()->set('security.password.symbols', false);

        $labelsTanpaSimbol = array_column(PasswordPolicy::checklist(), 'label');

        $this->assertNotContains('Ada karakter khusus (!@#$%...)', $labelsTanpaSimbol);
    }
}
