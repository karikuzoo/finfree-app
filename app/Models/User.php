<?php

namespace App\Models;

use App\Enums\RiskProfile;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'risk_profile',
        'currency_preference',
        'prefers_syariah',
        'birth_date',
        'nationality',
        'phone',
        'occupation',
    ];

    /**
     * Nilai awal untuk model yang baru dibuat.
     *
     * Kolomnya memang sudah punya DEFAULT di database, tetapi Eloquent tidak
     * membaca ulang baris setelah menyimpan — sehingga tepat setelah
     * pendaftaran, `$user->risk_profile` akan bernilai null di request yang
     * sama. Akibatnya form preferensi tampil tanpa pilihan apa pun sampai
     * halaman dimuat ulang. Menyatakannya di sini membuat model di memori
     * konsisten dengan isi database sejak detik pertama.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'risk_profile' => RiskProfile::Moderate->value,
        'currency_preference' => 'IDR',
        'prefers_syariah' => false,
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
            'risk_profile' => RiskProfile::class,
            'prefers_syariah' => 'boolean',
            // 'date' (bukan 'datetime') supaya diserialisasi sebagai
            // "1995-08-17", format yang langsung diterima <input type="date">
            // tanpa perlu dipotong di frontend.
            'birth_date' => 'date:Y-m-d',
        ];
    }

    /**
     * Ikut dikirim ke frontend setiap kali user diserialisasi.
     *
     * Frontend menerima URL siap pakai, bukan jalur mentah — supaya tidak ada
     * halaman yang menyusun URL penyimpanan sendiri lalu ikut rusak bila kelak
     * berpindah ke S3 atau CDN.
     *
     * @var list<string>
     */
    protected $appends = ['avatar_url', 'initials'];

    /**
     * URL foto profil, atau null bila pengguna belum mengunggah.
     *
     * Sengaja LEWAT route avatars.show (AvatarFileController), BUKAN
     * Storage::disk('public')->url() (yang mengarah ke symlink
     * public/storage) — lihat komentar panjang di AvatarFileController
     * soal kenapa symlink itu tidak diandalkan lagi di Windows.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? route('avatars.show', ['filename' => basename($this->avatar_path)])
            : null;
    }

    /**
     * Inisial nama untuk avatar cadangan — "Muhammad Ihsan" menjadi "MI".
     * Dihitung di backend agar seluruh tampilan memakai aturan yang sama.
     */
    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_map(fn (string $p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: '?';
    }

    public function goals(): HasMany
    {
        return $this->hasMany(FinancialGoal::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}