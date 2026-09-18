<?php

namespace App\Services\Reporting;

use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared by every report: the balance of every account of a given type,
 * from POSTED journal lines only, computed by Postgres (SUM over the
 * NUMERIC columns — see JournalPostingService's docblock for why this
 * matters). No separate ledger or report table — spec is explicit that
 * reports query journal_lines directly (STEP 12).
 */
class AccountBalanceCalculator
{
    /**
     * One row per account that had posted activity in the window, with its
     * balance. Accounts with no posted activity in the window simply don't
     * appear — a report doesn't list every account in the Chart of
     * Accounts, only the ones that moved.
     *
     * @return Collection<int, object{id: int, code: string, name: string, balance: string}>
     */
    public function breakdown(Company $company, string $type, ?string $dateFrom, ?string $dateTo, bool $debitNormal): Collection
    {
        return $this->query($company, $type, $dateFrom, $dateTo, $debitNormal)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->selectRaw("accounts.id, accounts.code, accounts.name, SUM({$this->delta($debitNormal)}) as balance")
            ->get();
    }

    public function total(Company $company, string $type, ?string $dateFrom, ?string $dateTo, bool $debitNormal): string
    {
        return (string) $this->query($company, $type, $dateFrom, $dateTo, $debitNormal)
            ->selectRaw("COALESCE(SUM({$this->delta($debitNormal)}), 0) as total")
            ->value('total');
    }

    private function query(Company $company, string $type, ?string $dateFrom, ?string $dateTo, bool $debitNormal)
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.company_id', $company->id)
            ->where('accounts.type', $type)
            ->where('journal_entries.status', JournalEntry::POSTED)
            ->when($dateFrom, fn ($q) => $q->where('journal_entries.transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('journal_entries.transaction_date', '<=', $dateTo));
    }

    private function delta(bool $debitNormal): string
    {
        return $debitNormal
            ? 'journal_lines.debit - journal_lines.credit'
            : 'journal_lines.credit - journal_lines.debit';
    }
}
