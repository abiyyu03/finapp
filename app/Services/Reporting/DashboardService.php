<?php

namespace App\Services\Reporting;

use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard (spec STEP 16). Every figure here is derived from accounting
 * data at render time — nothing is cached or computed independently, so
 * the dashboard can never drift from the reports (spec §21: "Dashboard
 * bukan source of truth").
 */
class DashboardService
{
    public function __construct(private readonly ProfitLossService $profitLossService) {}

    /**
     * @return array{cashBalance: string, bankBalance: string, totalRevenue: string, totalExpense: string, netProfit: string, recentJournals: Collection, recentCashTransactions: Collection}
     */
    public function forCompany(Company $company): array
    {
        $profitLoss = $this->profitLossService->forPeriod($company, null, null);

        return [
            'cashBalance' => $this->balanceForLinkedAccounts($company, 'cash_accounts'),
            'bankBalance' => $this->balanceForLinkedAccounts($company, 'bank_accounts'),
            'totalRevenue' => $profitLoss['totalRevenue'],
            'totalExpense' => $profitLoss['totalExpense'],
            'netProfit' => $profitLoss['netProfit'],
            'recentJournals' => $company->journalEntries()->latest()->limit(5)->get(),
            'recentCashTransactions' => $company->cashTransactions()->with(['cashAccount', 'counterAccount'])->latest()->limit(5)->get(),
        ];
    }

    /**
     * Cash/Bank Balance: sum of posted activity on the ASSET accounts that
     * are actually linked from a CashAccount/BankAccount row — not "every
     * ASSET account", so this stays correct even if other ASSET accounts
     * (e.g. a future Accounts Receivable) exist.
     */
    private function balanceForLinkedAccounts(Company $company, string $linkTable): string
    {
        $total = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.status', JournalEntry::POSTED)
            ->whereIn('journal_lines.account_id', function ($query) use ($company, $linkTable) {
                $query->select('account_id')->from($linkTable)->where('company_id', $company->id);
            })
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as total')
            ->value('total');

        return (string) $total;
    }
}
