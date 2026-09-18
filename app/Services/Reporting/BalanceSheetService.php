<?php

namespace App\Services\Reporting;

use App\Models\Account;
use App\Models\Company;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/**
 * Balance Sheet (spec STEP 14). Equity treatment is documented in
 * docs/accounting/balance-sheet.md — read that before changing this class.
 */
class BalanceSheetService
{
    public function __construct(
        private readonly AccountBalanceCalculator $calculator,
        private readonly ProfitLossService $profitLossService,
    ) {}

    /**
     * @return array{
     *     assets: Collection,
     *     liabilities: Collection,
     *     equityAccounts: Collection,
     *     currentYearEarnings: string,
     *     totalAssets: string,
     *     totalLiabilities: string,
     *     totalEquityAccounts: string,
     *     totalEquity: string,
     *     totalLiabilitiesAndEquity: string,
     *     isBalanced: bool,
     * }
     */
    public function asOf(Company $company, string $asOfDate): array
    {
        $assets = $this->calculator->breakdown($company, Account::ASSET, null, $asOfDate, debitNormal: true);
        $liabilities = $this->calculator->breakdown($company, Account::LIABILITY, null, $asOfDate, debitNormal: false);
        $equityAccounts = $this->calculator->breakdown($company, Account::EQUITY, null, $asOfDate, debitNormal: false);

        $totalAssets = $this->calculator->total($company, Account::ASSET, null, $asOfDate, debitNormal: true);
        $totalLiabilities = $this->calculator->total($company, Account::LIABILITY, null, $asOfDate, debitNormal: false);
        $totalEquityAccounts = $this->calculator->total($company, Account::EQUITY, null, $asOfDate, debitNormal: false);

        // Current Year Earnings: net P&L since inception through the as-of
        // date (docs/accounting/balance-sheet.md) — not a posted account,
        // computed live from the same source ProfitLossService uses.
        $currentYearEarnings = $this->profitLossService->forPeriod($company, null, $asOfDate)['netProfit'];

        $totalEquity = Decimal::add($totalEquityAccounts, $currentYearEarnings);
        $totalLiabilitiesAndEquity = Decimal::add($totalLiabilities, $totalEquity);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equityAccounts' => $equityAccounts,
            'currentYearEarnings' => $currentYearEarnings,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquityAccounts' => $totalEquityAccounts,
            'totalEquity' => $totalEquity,
            'totalLiabilitiesAndEquity' => $totalLiabilitiesAndEquity,
            // biz §20: a mismatch is an accounting bug to surface, never a
            // number to quietly display — see the service's own guarantee
            // in docs/accounting/balance-sheet.md for why this should
            // always be true given correctly posted journals.
            'isBalanced' => Decimal::equals($totalAssets, $totalLiabilitiesAndEquity),
        ];
    }
}
