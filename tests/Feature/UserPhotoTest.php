<?php

namespace Tests\Feature;

use App\Livewire\ManageUsers;
use App\Models\Entity;
use App\Models\User;
use App\Support\EntityContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Foto pengguna: diunggah di Manage User, tampil di bilah atas.
 *
 * Yang paling mudah terlewat bukan fitur unggahnya, melainkan berkas lama yang
 * tertinggal di disk setiap kali foto diganti atau penggunanya dihapus.
 */
class UserPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Entity $entitas;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->entitas = Entity::where('code', 'HERBATECH')->firstOrFail();
        app(EntityContext::class)->use($this->entitas->id);
        $this->actingAs(User::where('email', 'superadmin@emc.co.id')->firstOrFail());
    }

    private function pengguna(string $email = 'foto@contoh.test'): User
    {
        $pengguna = User::create([
            'name' => 'Budi Santoso', 'email' => $email, 'password' => bcrypt('x'),
            'is_active' => true, 'entity_id' => $this->entitas->id,
        ]);
        $pengguna->assignRole('Viewer');

        return $pengguna;
    }

    public function test_a_photo_can_be_uploaded_for_a_user(): void
    {
        $pengguna = $this->pengguna();

        Livewire::test(ManageUsers::class)
            ->call('openEdit', $pengguna->id)
            ->set('photoUpload', UploadedFile::fake()->image('budi.jpg', 200, 200))
            ->call('saveUser')
            ->assertHasNoErrors();

        $tersimpan = $pengguna->fresh()->photo_path;

        $this->assertNotNull($tersimpan);
        Storage::disk('public')->assertExists($tersimpan);
        $this->assertStringStartsWith('avatar/', $tersimpan);
    }

    public function test_replacing_a_photo_deletes_the_previous_file(): void
    {
        $pengguna = $this->pengguna();

        Livewire::test(ManageUsers::class)
            ->call('openEdit', $pengguna->id)
            ->set('photoUpload', UploadedFile::fake()->image('pertama.jpg'))
            ->call('saveUser');

        $pertama = $pengguna->fresh()->photo_path;

        Livewire::test(ManageUsers::class)
            ->call('openEdit', $pengguna->id)
            ->set('photoUpload', UploadedFile::fake()->image('kedua.jpg'))
            ->call('saveUser');

        $kedua = $pengguna->fresh()->photo_path;

        $this->assertNotSame($pertama, $kedua);
        Storage::disk('public')->assertExists($kedua);
        // Tanpa ini, tiap penggantian meninggalkan berkas yatim di disk.
        Storage::disk('public')->assertMissing($pertama);
    }

    public function test_a_photo_can_be_removed_back_to_initials(): void
    {
        $pengguna = $this->pengguna();

        Livewire::test(ManageUsers::class)
            ->call('openEdit', $pengguna->id)
            ->set('photoUpload', UploadedFile::fake()->image('budi.jpg'))
            ->call('saveUser');

        $berkas = $pengguna->fresh()->photo_path;

        Livewire::test(ManageUsers::class)
            ->call('openEdit', $pengguna->id)
            ->set('removePhoto', true)
            ->call('saveUser');

        $this->assertNull($pengguna->fresh()->photo_path);
        Storage::disk('public')->assertMissing($berkas);
        $this->assertSame('BS', $pengguna->fresh()->initials());
    }

    public function test_deleting_a_user_also_deletes_the_photo_file(): void
    {
        $pengguna = $this->pengguna();

        Livewire::test(ManageUsers::class)
            ->call('openEdit', $pengguna->id)
            ->set('photoUpload', UploadedFile::fake()->image('budi.jpg'))
            ->call('saveUser');

        $berkas = $pengguna->fresh()->photo_path;

        Livewire::test(ManageUsers::class)
            ->call('confirmDelete', $pengguna->id)
            ->call('deleteUser');

        $this->assertDatabaseMissing('users', ['id' => $pengguna->id]);
        Storage::disk('public')->assertMissing($berkas);
    }

    public function test_only_small_image_files_are_accepted(): void
    {
        $pengguna = $this->pengguna();

        // Bukan gambar.
        Livewire::test(ManageUsers::class)
            ->call('openEdit', $pengguna->id)
            ->set('photoUpload', UploadedFile::fake()->create('daftar.pdf', 100, 'application/pdf'))
            ->call('saveUser')
            ->assertHasErrors('photoUpload');

        // Gambar terlalu besar (> 1 MB).
        Livewire::test(ManageUsers::class)
            ->call('openEdit', $pengguna->id)
            ->set('photoUpload', UploadedFile::fake()->image('besar.jpg')->size(2048))
            ->call('saveUser')
            ->assertHasErrors('photoUpload');

        $this->assertNull($pengguna->fresh()->photo_path);
    }

    public function test_the_photo_appears_in_the_navbar_and_falls_back_to_initials(): void
    {
        $pengguna = $this->pengguna('navbar@contoh.test');
        $pengguna->syncRoles(['Super Admin']);
        $this->actingAs($pengguna);

        // Tanpa foto: inisial namanya.
        $this->get(route('dashboard'))->assertOk()->assertSee('BS');

        Livewire::test(ManageUsers::class)
            ->call('openEdit', $pengguna->id)
            ->set('photoUpload', UploadedFile::fake()->image('budi.jpg'))
            ->call('saveUser');

        // actingAs menahan objek pengguna yang lama; permintaan HTTP sungguhan
        // memuatnya ulang, jadi disegarkan supaya ujinya setia pada kenyataan.
        $this->actingAs($pengguna->fresh());

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('storage/'.$pengguna->fresh()->photo_path, false);
    }

    public function test_a_missing_file_falls_back_to_initials_instead_of_a_broken_image(): void
    {
        $pengguna = $this->pengguna();
        $pengguna->update(['photo_path' => 'avatar/sudah-hilang.jpg']);

        $this->assertNull($pengguna->fresh()->photoUrl());
        $this->assertSame('BS', $pengguna->fresh()->initials());
    }
}
