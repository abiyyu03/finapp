<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    public const CASH_IN = 'CASH_IN';

    public const CASH_OUT = 'CASH_OUT';

    public const DRAFT = 'DRAFT';

    public const POSTED = 'POSTED';

    protected $fillable = [
        'company_id', 'transaction_number', 'transaction_type', 'transaction_date',
        'cash_account_id', 'counter_account_id', 'amount', 'description',
        'status', 'journal_entry_id', 'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::POSTED;
    }

    public function isCashIn(): bool
    {
        return $this->transaction_type === self::CASH_IN;
    }

    public function isCashOut(): bool
    {
        return $this->transaction_type === self::CASH_OUT;
    }

    public static function nextTransactionNumber(Company $company): string
    {
        $next = $company->cashTransactions()->count() + 1;

        while ($company->cashTransactions()->where('transaction_number', $number = sprintf('CT-%06d', $next))->exists()) {
            $next++;
        }

        return $number;
    }
}
