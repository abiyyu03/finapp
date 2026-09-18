<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashAccountRequest;
use App\Http\Requests\UpdateCashAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CashAccountController extends Controller
{
    /**
     * Every lookup is scoped to `$company->cashAccounts()`, never
     * `CashAccount::find()` (spec §16), same pattern as everywhere else.
     */
    public function index(Request $request)
    {
        Gate::authorize('account.view');

        $cashAccounts = $request->user()->activeCompany()
            ->cashAccounts()
            ->with('account')
            ->orderBy('name')
            ->paginate(20);

        return view('cash-accounts.index', ['cashAccounts' => $cashAccounts]);
    }

    public function create(Request $request)
    {
        Gate::authorize('account.create');

        return view('cash-accounts.create', [
            'accountOptions' => $this->accountOptions($request),
        ]);
    }

    public function store(StoreCashAccountRequest $request): RedirectResponse
    {
        $cashAccount = $request->user()->activeCompany()->cashAccounts()->create($request->validated());

        return redirect()->route('cash-accounts.index')->with('success', "Cash account \"{$cashAccount->name}\" was created.");
    }

    public function edit(Request $request, string $cashAccount)
    {
        Gate::authorize('account.create');

        $cashAccount = $request->user()->activeCompany()->cashAccounts()->findOrFail($cashAccount);

        return view('cash-accounts.edit', [
            'cashAccount' => $cashAccount,
            'accountOptions' => $this->accountOptions($request, alwaysInclude: $cashAccount->account_id),
        ]);
    }

    public function update(UpdateCashAccountRequest $request, string $cashAccount): RedirectResponse
    {
        $cashAccount = $request->user()->activeCompany()->cashAccounts()->findOrFail($cashAccount);
        $cashAccount->update($request->validated());

        return redirect()->route('cash-accounts.index')->with('success', "Cash account \"{$cashAccount->name}\" was updated.");
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
