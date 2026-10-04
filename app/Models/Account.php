<?php

namespace App\Models;

use App\Enums\AccountKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rekening atau aset milik pengguna.
 *
 * Tidak ada atribut `balance` di sini. Saldo menyentuh transaksi masuk
 * MAUPUN transfer masuk dari rekening lain, jadi menghitungnya per model
 * berarti satu kueri per rekening — N+1 yang persis muncul di halaman yang
 * paling sering dibuka. AccountBalanceService menghitung seluruhnya
 * sekaligus; lihat di sana.
 */
class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'kind',
        'currency',
        'institution',
        'opening_balance',
        'units',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AccountKind::class,
            'opening_balance' => 'decimal:2',
            'units' => 'decimal:4',
        ];
    }

    /**
     * Satuan jumlah aset rekening ini: gram/lot/unit menurut jenisnya, atau
     * kode mata uang untuk valas ("USD"). Pakai ini, bukan
     * AccountKind::satuan(), untuk satu rekening — satuan valas per rekening.
     */
    public function satuan(): ?string
    {
        return $this->kind === AccountKind::ForeignCurrency
            ? $this->currency
            : $this->kind->satuan();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Transaksi yang BERASAL dari rekening ini. Lihat juga incomingTransfers. */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Transfer yang MASUK ke rekening ini. Terpisah dari `transactions`
     * karena menunjuk kolom yang berbeda — melewatkannya membuat saldo
     * rekening tujuan selalu kurang sebesar seluruh transfer masuknya.
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_account_id');
    }

    // Relasi ke FinancialGoal ("target yang dananya ditandai di rekening
    // ini") menyusul bersama kolom financial_goals.account_id pada fase
    // Target tabungan — belum dideklarasikan di sini supaya tidak ada
    // relasi yang menunjuk kolom yang belum ada.
}
