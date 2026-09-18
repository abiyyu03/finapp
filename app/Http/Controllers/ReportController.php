<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\Reporting\BalanceSheetService;
use App\Services\Reporting\GeneralLedgerService;
use App\Services\Reporting\ProfitLossService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function generalLedger(Request $request, GeneralLedgerService $service)
    {
        Gate::authorize('report.view');

        $company = $request->user()->activeCompany();

        $accountOptions = $company->accounts()
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account) => [$account->id => "{$account->code} — {$account->name}"])
            ->all();

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $ledger = null;
        $account = null;

        // The account id is only ever resolved against this company's own
        // accounts (spec §16) — an id for another company's account simply
        // isn't in this relation, so it's treated the same as "none selected".
        if ($accountId = $request->query('account_id')) {
            $account = $company->accounts()->find($accountId);

            if ($account) {
                $ledger = $service->forAccount($account, $dateFrom, $dateTo);
            }
        }

        return view('reports.general-ledger', [
            'accountOptions' => $accountOptions,
            'selectedAccount' => $account,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'ledger' => $ledger,
        ]);
    }

    public function profitLoss(Request $request, ProfitLossService $service)
    {
        Gate::authorize('report.view');

        $company = $request->user()->activeCompany();
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        return view('reports.profit-loss', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'report' => $service->forPeriod($company, $dateFrom, $dateTo),
        ]);
    }

    public function balanceSheet(Request $request, BalanceSheetService $service)
    {
        Gate::authorize('report.view');

        $company = $request->user()->activeCompany();
        $asOfDate = $request->query('as_of_date') ?: Carbon::today()->toDateString();

        return view('reports.balance-sheet', [
            'asOfDate' => $asOfDate,
            'report' => $service->asOf($company, $asOfDate),
        ]);
    }
}
