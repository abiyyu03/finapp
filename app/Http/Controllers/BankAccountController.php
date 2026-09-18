<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BankAccountController extends Controller
{
    /**
     * Every lookup is scoped to `$company->bankAccounts()`, never
     * `BankAccount::find()` (spec §16).
     *
     * Nothing in this controller ever passes a bank account (or its
     * account_number) to Log::* or an exception message — the only place
     * the raw number is read is the edit form (spec STEP 9: don't log or
     * expose it unnecessarily).
     */
    public function index(Request $request)
    {
        Gate::authorize('account.view');

        $bankAccounts = $request->user()->activeCompany()
            ->bankAccounts()
            ->with('account')
            ->orderBy('bank_name')
            ->paginate(20);

        return view('bank-accounts.index', ['bankAccounts' => $bankAccounts]);
    }

    public function create(Request $request)
    {
        Gate::authorize('account.create');

        return view('bank-accounts.create', [
            'accountOptions' => $this->accountOptions($request),
        ]);
    }

    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $bankAccount = $request->user()->activeCompany()->bankAccounts()->create($request->validated());

        return redirect()->route('bank-accounts.index')->with('success', "Bank account \"{$bankAccount->account_name}\" was created.");
    }

    public function edit(Request $request, string $bankAccount)
    {
        Gate::authorize('account.create');

        $bankAccount = $request->user()->activeCompany()->bankAccounts()->findOrFail($bankAccount);

        return view('bank-accounts.edit', [
            'bankAccount' => $bankAccount,
            'accountOptions' => $this->accountOptions($request, alwaysInclude: $bankAccount->account_id),
        ]);
    }

    public function update(UpdateBankAccountRequest $request, string $bankAccount): RedirectResponse
    {
        $bankAccount = $request->user()->activeCompany()->bankAccounts()->findOrFail($bankAccount);
        $bankAccount->update($request->validated());

        return redirect()->route('bank-accounts.index')->with('success', "Bank account \"{$bankAccount->account_name}\" was updated.");
    }

    /**
     * @return array<int, string>
     */
    private function accountOptions(Request $request, ?int $alwaysInclude = null): array
    {
        return $request->user()->activeCompany()
            ->accounts()
            ->where('type', Account::ASSET)
            ->where(function ($query) use ($alwaysInclude) {
                $query->where('status', Account::ACTIVE);

                if ($alwaysInclude) {
                    $query->orWhere('id', $alwaysInclude);
                }
            })
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account) => [$account->id => "{$account->code} — {$account->name}"])
            ->all();
    }
}
