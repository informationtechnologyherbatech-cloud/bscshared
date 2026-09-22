<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logo & favicon: halaman login memakai logo holding (EMC dengan teks) dan
 * menampilkan logo keempat entitas; halaman admin mengikuti entitas aktif.
 * Berkas logo ada di public/images.
 */
class EntityLogoTest extends TestCase
{
    use RefreshDatabase;

    private function user(?string $kode): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::create([
            'name' => 'Pengguna', 'email' => 'u'.($kode ?? 'holding').'@contoh.test', 'password' => bcrypt('x'), 'is_active' => true,
            'entity_id' => $kode ? Entity::where('code', $kode)->value('id') : null,
        ]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_the_logo_files_exist(): void
    {
        foreach (config('entity.profiles') as $kode => $p) {
            $this->assertFileExists(public_path($p['logo']), "Logo {$kode} tidak ditemukan.");
        }
        $this->assertFileExists(public_path(config('entity.holding.logo_text')));
    }

    public function test_the_login_page_shows_the_group_logo_and_every_entity_logo(): void
    {
        $halaman = $this->get(route('login'))->assertOk();

        $halaman->assertSee('logo%20emc%20-%20text.webp', false);
        foreach (['herbaemas', 'herbatech', 'aej', 'erdigma'] as $nama) {
            $halaman->assertSee('logo%20'.$nama.'.webp', false);
        }
        $this->assertCount(4, group_entity_logos());
    }

    public function test_the_admin_logo_and_favicon_follow_the_users_entity(): void
    {
        $this->actingAs($this->user('HERBATECH'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<link rel="icon" href="'.asset('images/logo%20herbatech.webp').'">', false)
            ->assertSee('src="'.asset('images/logo%20herbatech.webp').'"', false)
            ->assertDontSee('logo%20erdigma.webp', false);
    }

    public function test_a_holding_user_sees_the_logo_of_the_entity_being_viewed(): void
    {
        $holding = $this->user(null);
        $this->actingAs($holding);
        $konteks = app(EntityContext::class);

        $this->assertStringEndsWith('logo%20erdigma.webp', entity_logo()); // entitas bawaan

        $konteks->switchTo($holding, Entity::where('code', 'AEJ')->value('id'));
        $konteks->forget();
        $this->assertStringEndsWith('logo%20aej.webp', entity_logo());
        $this->assertStringEndsWith('logo%20aej.webp', entity_favicon());
    }

    public function test_without_a_user_the_installation_decides(): void
    {
        // Instalasi holding (phpunit.xml) → logo EMC.
        $this->assertStringEndsWith('logo%20emc.webp', entity_logo());

        config(['bsc.holding_mode' => false, 'bsc.default_entity' => 'HERBAEMAS']);
        $this->assertStringEndsWith('logo%20herbaemas.webp', entity_logo());
    }

    public function test_a_missing_file_falls_back_to_the_default_icon(): void
    {
        config(['entity.profiles.ERDIGMA.logo' => 'images/tidak-ada.webp', 'entity.profiles.ERDIGMA.favicon' => 'images/tidak-ada.webp']);
        $this->actingAs($this->user('ERDIGMA'));

        $this->assertNull(entity_logo());
        $this->assertSame(asset('favicon.ico'), entity_favicon());
    }
}
