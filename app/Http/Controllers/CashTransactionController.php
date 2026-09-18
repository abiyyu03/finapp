<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashInRequest;
use App\Http\Requests\StoreCashOutRequest;
use App\Models\Account;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Services\Cash\CashTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CashTransactionController extends Controller
{
    /**
     * Every lookup is scoped to `$company->cashTransactions()`, never
     * `CashTransaction::find()` (spec §16).
     */
    public function index(Request $request)
    {
        Gate::authorize('journal.view');

        $transactions = $request->user()->activeCompany()
            ->cashTransactions()
            ->with(['cashAccount', 'counterAccount'])
            ->when(
                in_array($request->query('type'), [CashTransaction::CASH_IN, CashTransaction::CASH_OUT], true),
                fn ($query) => $query->where('transaction_type', $request->query('type')),
            )
            ->when(
                in_array($request->query('status'), [CashTransaction::DRAFT, CashTransaction::POSTED], true),
                fn ($query) => $query->where('status', $request->query('status')),
            )
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(20);

        return view('cash-transactions.index', ['transactions' => $transactions]);
    }

    public function createCashIn(Request $request)
    {
        Gate::authorize('journal.create');

        return view('cash-transactions.create-in', [
            'cashAccountOptions' => $this->cashAccountOptions($request),
            'accountOptions' => $this->accountOptions($request),
        ]);
    }

    public function storeCashIn(StoreCashInRequest $request, CashTransactionService $service): RedirectResponse
    {
        $transaction = $service->createCashIn(
            $request->user()->activeCompany(),
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('cash-transactions.show', $transaction)
            ->with('success', "Cash In {$transaction->transaction_number} was saved as a draft.");
    }

    public function createCashOut(Request $request)
    {
        Gate::authorize('journal.create');

        return view('cash-transactions.create-out', [
            'cashAccountOptions' => $this->cashAccountOptions($request),
            'accountOptions' => $this->accountOptions($request),
        ]);
    }

    public function storeCashOut(StoreCashOutRequest $request, CashTransactionService $service): RedirectResponse
    {
        $transaction = $service->createCashOut(
            $request->user()->activeCompany(),
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('cash-transactions.show', $transaction)
            ->with('success', "Cash Out {$transaction->transaction_number} was saved as a draft.");
    }

    public function show(Request $request, string $transaction)
    {
        Gate::authorize('journal.view');

        $transaction = $request->user()->activeCompany()
            ->cashTransactions()
            ->with(['cashAccount.account', 'counterAccount', 'creator', 'poster', 'journalEntry'])
            ->findOrFail($transaction);

        return view('cash-transactions.show', ['transaction' => $transaction]);
    }

    public function post(Request $request, string $transaction, CashTransactionService $service): RedirectResponse
    {
        Gate::authorize('journal.post');

        $transaction = $request->user()->activeCompany()->cashTransactions()->findOrFail($transaction);

        if ($transaction->isPosted()) {
            return redirect()->route('cash-transactions.show', $transaction)
                ->with('info', "{$transaction->transaction_number} was already posted.");
        }

        try {
            $service->post($transaction, $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('cash-transactions.show', $transaction)->withErrors($e->errors());
        }

        return redirect()->route('cash-transactions.show', $transaction)
            ->with('success', "{$transaction->transaction_number} was posted.");
    }

    public function destroy(Request $request, string $transaction, CashTransactionService $service): RedirectResponse
    {
        Gate::authorize('journal.create');

        $transaction = $request->user()->activeCompany()->cashTransactions()->findOrFail($transaction);
        abort_if(! $transaction->isDraft(), 403, 'A posted cash transaction cannot be deleted (spec §13).');

        $service->deleteDraft($transaction);

        return redirect()->route('cash-transactions.index')->with('success', "{$transaction->transaction_number} was deleted.");
    }

    /**
     * @return array<int, string>
     */
    private function cashAccountOptions(Request $request): array
    {
        return $request->user()->activeCompany()
            ->cashAccounts()
            ->where('status', CashAccount::ACTIVE)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (CashAccount $cashAccount) => [$cashAccount->id => $cashAccount->name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function accountOptions(Request $request): array
    {
        return $request->user()->activeCompany()
            ->accounts()
            ->where('status', Account::ACTIVE)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account) => [$account->id => "{$account->code} — {$account->name}"])
            ->all();
    }
}
