<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        // Lokasi berkas foto di disk public; kosong = memakai inisial nama.
        'photo_path',
        'password',
        'is_active',
        'dept_code',
        'entity_id',
        'must_change_password',
        'password_changed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * Tandai bahwa kata sandi pengguna harus diganti pada akses berikutnya.
     */
    public function requirePasswordChange(bool $wajib = true): void
    {
        if ($this->must_change_password === $wajib) {
            return;
        }

        $this->forceFill(['must_change_password' => $wajib])->save();
    }

    /**
     * Entitas tempat pengguna bekerja. Kosong = pengguna level holding yang
     * dapat berpindah antarentitas.
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function isHoldingLevel(): bool
    {
        return $this->entity_id === null;
    }

    /**
     * URL foto pengguna, atau null bila belum mengunggah.
     *
     * Keberadaan berkasnya ikut diperiksa supaya foto yang hilang dari disk
     * tidak menyisakan gambar rusak di navbar — sama seperti logo entitas.
     */
    public function photoUrl(): ?string
    {
        if (! $this->photo_path || ! Storage::disk('public')->exists($this->photo_path)) {
            return null;
        }

        return asset('storage/'.ltrim($this->photo_path, '/'));
    }

    /** Inisial nama — dipakai bila belum ada foto. */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim((string) $this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($kata) => mb_strtoupper(mb_substr($kata, 0, 1)))
            ->implode('') ?: 'U';
    }
}
