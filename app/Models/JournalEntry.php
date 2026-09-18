<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    public const DRAFT = 'DRAFT';

    public const POSTED = 'POSTED';

    public const NORMAL = 'NORMAL';

    public const OPENING_BALANCE = 'OPENING_BALANCE';

    protected $fillable = [
        'company_id', 'journal_number', 'transaction_date', 'description',
        'status', 'journal_type', 'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
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
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
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

    public function isOpeningBalance(): bool
    {
        return $this->journal_type === self::OPENING_BALANCE;
    }

    /**
     * DB-level aggregates, not PHP arithmetic (spec §6) — the sum is
     * computed by Postgres over the NUMERIC(20,2) columns, not accumulated
     * as PHP floats.
     */
    public function totalDebit(): string
    {
        return (string) $this->lines()->sum('debit');
    }

    public function totalCredit(): string
    {
        return (string) $this->lines()->sum('credit');
    }

    /**
     * Generates the next sequential journal number for a company
     * (JRN-000001, JRN-000002, …). Retries on the rare unique-constraint
     * collision instead of taking a lock for what is, at this step, still
     * just draft numbering — STEP 6's posting is where a hard
     * transactional lock actually matters.
     */
    public static function nextJournalNumber(Company $company): string
    {
        $next = $company->journalEntries()->count() + 1;

        while ($company->journalEntries()->where('journal_number', $number = sprintf('JRN-%06d', $next))->exists()) {
            $next++;
        }

        return $number;
    }
}
