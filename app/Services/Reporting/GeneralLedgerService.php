<?php

namespace App\Services\Reporting;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * General Ledger (spec STEP 12): mutations of one account, POSTED journals
 * only, no separate ledger table — every figure here is computed straight
 * from journal_lines/journal_entries.
 *
 * Every sum and every running balance is computed by Postgres over the
 * NUMERIC(20,2) columns (SUM(...) and a window function), never by PHP
 * arithmetic on the values — same discipline as JournalPostingService,
 * for the same reason: no bcmath, no float, exact decimals only.
 */
class GeneralLedgerService
{
    /**
     * @return array{broughtForward: string, rows: Collection, endingBalance: string}
     */
    public function forAccount(Account $account, ?string $dateFrom, ?string $dateTo): array
    {
        $normalIsDebit = in_array($account->type, [Account::ASSET, Account::EXPENSE], true);
        $delta = $normalIsDebit
            ? 'journal_lines.debit - journal_lines.credit'
            : 'journal_lines.credit - journal_lines.debit';

        $baseQuery = fn () => DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->where('journal_entries.company_id', $account->company_id)
            ->where('journal_entries.status', JournalEntry::POSTED);

        $broughtForward = '0.00';

        if ($dateFrom) {
            $broughtForward = (string) $baseQuery()
                ->where('journal_entries.transaction_date', '<', $dateFrom)
                ->selectRaw("COALESCE(SUM({$delta}), 0) as brought_forward")
                ->value('brought_forward');
        }

        $rows = $baseQuery()
            ->when($dateFrom, fn ($q) => $q->where('journal_entries.transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('journal_entries.transaction_date', '<=', $dateTo))
            ->orderBy('journal_entries.transaction_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id')
            ->selectRaw(<<<SQL
                journal_entries.transaction_date as transaction_date,
                journal_entries.journal_number as journal_number,
                COALESCE(journal_lines.description, journal_entries.description) as description,
                journal_lines.debit as debit,
                journal_lines.credit as credit,
                SUM({$delta}) OVER (
                    ORDER BY journal_entries.transaction_date, journal_entries.id, journal_lines.id
                ) + ? as balance
            SQL, [$broughtForward])
            ->get();

        $endingBalance = $rows->isNotEmpty() ? (string) $rows->last()->balance : $broughtForward;

        return [
            'broughtForward' => $broughtForward,
            'rows' => $rows,
            'endingBalance' => $endingBalance,
        ];
    }
}
