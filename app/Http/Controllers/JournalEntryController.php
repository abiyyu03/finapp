<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalRequest;
use App\Http\Requests\UpdateJournalRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class JournalEntryController extends Controller
{
    /**
     * Every lookup is scoped to `$company->journalEntries()`, never
     * `JournalEntry::find()` (spec §16), same pattern as accounts.
     */
    public function index(Request $request)
    {
        Gate::authorize('journal.view');

        $company = $request->user()->activeCompany();

        $journals = $company->journalEntries()
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->when(
                in_array($request->query('status'), [JournalEntry::DRAFT, JournalEntry::POSTED], true),
                fn ($query) => $query->where('status', $request->query('status')),
            )
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(20);

        return view('journals.index', [
            'journals' => $journals,
            'hasOpeningBalance' => $company->journalEntries()->where('journal_type', JournalEntry::OPENING_BALANCE)->exists(),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('journal.create');

        return view('journals.create', [
            'accountOptions' => $this->accountOptions($request),
        ]);
    }

    public function store(StoreJournalRequest $request): RedirectResponse
    {
        $company = $request->user()->activeCompany();

        $journal = DB::transaction(function () use ($request, $company) {
            $journal = $company->journalEntries()->create([
                'journal_number' => JournalEntry::nextJournalNumber($company),
                'transaction_date' => $request->validated('transaction_date'),
                'description' => $request->validated('description'),
                'status' => JournalEntry::DRAFT,
                'created_by' => $request->user()->id,
            ]);

            $journal->lines()->createMany($this->linesFromRequest($request));

            return $journal;
        });

        return redirect()->route('journals.show', $journal)->with('success', "Journal {$journal->journal_number} was saved as a draft.");
    }

    public function show(Request $request, string $journal)
    {
        Gate::authorize('journal.view');

        $journal = $request->user()->activeCompany()
            ->journalEntries()
            ->with(['lines.account', 'creator', 'poster'])
            ->findOrFail($journal);

        return view('journals.show', ['journal' => $journal]);
    }

    public function edit(Request $request, string $journal)
    {
        Gate::authorize('journal.create');

        $journal = $this->findEditableDraft($request, $journal);

        return view('journals.edit', [
            'journal' => $journal->load('lines'),
            'accountOptions' => $this->accountOptions($request),
        ]);
    }

    public function update(UpdateJournalRequest $request, string $journal): RedirectResponse
    {
        $journal = $this->findEditableDraft($request, $journal);

        DB::transaction(function () use ($request, $journal) {
            $journal->update([
                'transaction_date' => $request->validated('transaction_date'),
                'description' => $request->validated('description'),
            ]);

            // Simplest correct way to sync a full-form-resubmit line list:
            // replace them. Only reachable while DRAFT — posted journals
            // never go through this path (spec §13).
            $journal->lines()->delete();
            $journal->lines()->createMany($this->linesFromRequest($request));
        });

        return redirect()->route('journals.show', $journal)->with('success', "Journal {$journal->journal_number} was updated.");
    }

    public function destroy(Request $request, string $journal): RedirectResponse
    {
        Gate::authorize('journal.create');

        $journal = $this->findEditableDraft($request, $journal);
        $journal->delete();

        return redirect()->route('journals.index')->with('success', "Journal {$journal->journal_number} was deleted.");
    }

    /**
     * STEP 6 — the only place in this controller that changes a journal to
     * POSTED, and it does so purely by calling the canonical
     * JournalPostingService (spec §22). No accounting logic lives here.
     */
    public function post(Request $request, string $journal, JournalPostingService $postingService): RedirectResponse
    {
        Gate::authorize('journal.post');

        $journal = $request->user()->activeCompany()->journalEntries()->findOrFail($journal);

        if ($journal->isPosted()) {
            return redirect()->route('journals.show', $journal)->with('info', "Journal {$journal->journal_number} was already posted.");
        }

        try {
            $postingService->post($journal, $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('journals.show', $journal)->withErrors($e->errors());
        }

        return redirect()->route('journals.show', $journal)->with('success', "Journal {$journal->journal_number} was posted.");
    }

    private function findEditableDraft(Request $request, string $journal): JournalEntry
    {
        $journal = $request->user()->activeCompany()->journalEntries()->findOrFail($journal);

        abort_if(! $journal->isDraft(), 403, 'A posted journal cannot be edited or deleted (spec §13).');

        return $journal;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function linesFromRequest(Request $request): array
    {
        return collect($request->validated('lines'))
            ->map(fn (array $line) => [
                'account_id' => $line['account_id'],
                'description' => $line['description'] ?? null,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ])
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
