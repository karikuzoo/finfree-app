<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'type',
        'name',
        'amount',
        'to_account_id',
        'debt_id',
        'category',
        'occurred_on',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    /**
     * Pengaruh transaksi ini terhadap saldo `$accountId` — positif menambah,
     * negatif mengurangi, nol bila tidak menyentuhnya sama sekali.
     *
     * Satu-satunya tempat aturan tanda ditulis. Menyebarkannya ke beberapa
     * tempat adalah cara paling pasti membuat saldo di satu halaman berbeda
     * dari halaman lain.
     */
    public function effectOn(int $accountId): float
    {
        $nominal = (float) $this->amount;

        if ($this->type === TransactionType::Transfer && $this->to_account_id === $accountId) {
            return $nominal;
        }

        if ($this->account_id !== $accountId) {
            return 0.0;
        }

        return $this->type->menambahSaldo() ? $nominal : -$nominal;
    }

    /** Transaksi pada bulan tertentu, format "YYYY-MM". */
    public function scopeInMonth(Builder $query, string $bulan): Builder
    {
        return $query->whereBetween('occurred_on', [
            $bulan.'-01',
            date('Y-m-t', strtotime($bulan.'-01')),
        ]);
    }

    /** Hanya yang dihitung sebagai arus kas — lihat TransactionType::arusKas(). */
    public function scopeCashFlow(Builder $query): Builder
    {
        return $query->whereIn('type', array_column(
            array_filter(TransactionType::cases(), fn (TransactionType $t) => $t->arusKas()),
            'value',
        ));
    }
}
