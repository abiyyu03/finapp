<?php

namespace App\Services\Reporting;

use App\Models\Account;
use App\Models\Company;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/**
 * Profit & Loss (spec STEP 13): Revenue − Expense over a period, POSTED
 * journals only.
 */
class ProfitLossService
{
    public function __construct(private readonly AccountBalanceCalculator $calculator) {}

    /**
     * @return array{revenueLines: Collection, expenseLines: Collection, totalRevenue: string, totalExpense: string, netProfit: string}
     */
    public function forPeriod(Company $company, ?string $dateFrom, ?string $dateTo): array
    {
        $revenueLines = $this->calculator->breakdown($company, Account::REVENUE, $dateFrom, $dateTo, debitNormal: false);
        $expenseLines = $this->calculator->breakdown($company, Account::EXPENSE, $dateFrom, $dateTo, debitNormal: true);

        $totalRevenue = $this->calculator->total($company, Account::REVENUE, $dateFrom, $dateTo, debitNormal: false);
        $totalExpense = $this->calculator->total($company, Account::EXPENSE, $dateFrom, $dateTo, debitNormal: true);

        return [
            'revenueLines' => $revenueLines,
            'expenseLines' => $expenseLines,
            'totalRevenue' => $totalRevenue,
            'totalExpense' => $totalExpense,
            'netProfit' => Decimal::subtract($totalRevenue, $totalExpense),
        ];
    }
}
