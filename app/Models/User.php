<?php

namespace App\Models;

use App\Enums\RiskProfile;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        ];
    }
}
