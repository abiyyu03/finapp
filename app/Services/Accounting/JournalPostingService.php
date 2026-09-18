<?php

namespace App\Services\Accounting;

use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one canonical accounting posting service (spec §22). Journal Entry,
 * Cash In, and Cash Out all end here — nothing else in this codebase is
 * allowed to flip a journal to POSTED or write to General Ledger data.
 */
class JournalPostingService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @throws ValidationException when the journal fails any posting rule.
     */
    public function post(JournalEntry $journal, User $user): JournalEntry
    {
        return DB::transaction(function () use ($journal, $user) {
            // Lock the row before touching anything — two overlapping POST
            // requests for the same journal must not both succeed (biz §33).
            $locked = JournalEntry::whereKey($journal->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPosted()) {
                // Idempotent no-op: the first call already produced the one
                // financial effect and the one audit record this journal
                // gets. A second call (double-click, retry) changes nothing.
                return $locked;
            }

            $lines = $locked->lines()->with('account')->get();

            $this->assertHasEnoughLines($lines);
            $this->assertEveryLineIsValid($locked, $lines);
            $this->assertBalanced($locked);

            $locked->update([
                'status' => JournalEntry::POSTED,
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);

            $this->auditLogger->log(
                company: $locked->company,
                user: $user,
                action: AuditLog::POST,
                resourceType: JournalEntry::class,
                resourceId: $locked->id,
                before: ['status' => JournalEntry::DRAFT],
                after: ['status' => JournalEntry::POSTED, 'journal_number' => $locked->journal_number],
            );

            return $locked->fresh();
        });
    }

    private function assertHasEnoughLines(Collection $lines): void
    {
        if ($lines->count() < 2) {
            throw ValidationException::withMessages([
                'journal' => 'A journal needs at least two lines to be posted (spec §12).',
            ]);
        }
    }

    private function assertEveryLineIsValid(JournalEntry $journal, Collection $lines): void
    {
        foreach ($lines as $line) {
            $account = $line->account;

            if ($account->company_id !== $journal->company_id) {
                throw ValidationException::withMessages([
                    'journal' => "Line references account {$account->code}, which belongs to another company.",
                ]);
            }

            if (! $account->isActive()) {
                throw ValidationException::withMessages([
                    'journal' => "Account {$account->code} — {$account->name} is inactive and cannot be posted to.",
                ]);
            }

            $debitPositive = $line->debit > 0;
            $creditPositive = $line->credit > 0;

            if ($debitPositive && $creditPositive) {
                throw ValidationException::withMessages([
                    'journal' => "The line on {$account->code} has both a debit and a credit amount.",
                ]);
            }

            if (! $debitPositive && ! $creditPositive) {
                throw ValidationException::withMessages([
                    'journal' => "The line on {$account->code} has neither a debit nor a credit amount.",
                ]);
            }
        }
    }

    /**
     * Debit = Credit, compared as the exact decimal strings Postgres
     * returns from SUM() over the NUMERIC(20,2) columns — never cast to
     * PHP float (spec §6). Confirmed empirically: pdo_pgsql returns
     * `sum()` over a numeric column as a string, not a float.
     */
    private function assertBalanced(JournalEntry $journal): void
    {
        $totalDebit = $journal->lines()->sum('debit');
        $totalCredit = $journal->lines()->sum('credit');

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'journal' => "Total debit ({$totalDebit}) does not equal total credit ({$totalCredit}).",
            ]);
        }
    }
}
