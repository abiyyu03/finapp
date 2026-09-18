<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    /**
     * Every lookup below goes through `$company->accounts()`, never
     * `Account::find()` — an id for another company's account simply isn't
     * in this relation, so it 404s instead of leaking (spec §16).
     */
    public function index(Request $request)
    {
        Gate::authorize('account.view');

        $accounts = $request->user()->activeCompany()
            ->accounts()
            ->with('parent')
            ->orderBy('code')
            ->paginate(20);

        return view('accounts.index', ['accounts' => $accounts]);
    }

    public function create(Request $request)
    {
        Gate::authorize('account.create');

        return view('accounts.create', [
            'parentOptions' => $this->parentOptions($request),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = $request->user()->activeCompany()->accounts()->create($request->validated());

        return redirect()->route('accounts.index')->with('success', "Account {$account->code} — {$account->name} was created.");
    }

    public function edit(Request $request, string $account)
    {
        Gate::authorize('account.create');

        $account = $request->user()->activeCompany()->accounts()->findOrFail($account);

        return view('accounts.edit', [
            'account' => $account,
            'parentOptions' => $this->parentOptions($request, exclude: $account->id),
        ]);
    }

    public function update(UpdateAccountRequest $request, string $account, AuditLogger $auditLogger): RedirectResponse
    {
        $account = $request->user()->activeCompany()->accounts()->findOrFail($account);
        $before = $account->only(['code', 'name', 'type', 'parent_id', 'status']);

        $account->update($request->validated());

        $auditLogger->log(
            company: $request->user()->activeCompany(),
            user: $request->user(),
            action: AuditLog::UPDATE,
            resourceType: Account::class,
            resourceId: $account->id,
            before: $before,
            after: $account->only(['code', 'name', 'type', 'parent_id', 'status']),
        );

        return redirect()->route('accounts.index')->with('success', "Account {$account->code} — {$account->name} was updated.");
    }

    /**
     * @return array<int, string>
     */
    private function parentOptions(Request $request, ?int $exclude = null): array
    {
        return $request->user()->activeCompany()
            ->accounts()
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account) => [$account->id => "{$account->code} — {$account->name}"])
            ->all();
    }
}
