<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * STEP 7 — Opening Balance. This is not a second posting mechanism: it
 * builds an ordinary JournalEntry (tagged journal_type = OPENING_BALANCE)
 * and posts it through the exact same JournalPostingService as everything
 * else (spec §22 / STEP 7: "Do not create another posting mechanism").
 */
class OpeningBalanceController extends Controller
{
    public function show(Request $request)
    {
        Gate::authorize('journal.create');

        $company = $request->user()->activeCompany();
        $existing = $company->journalEntries()->where('journal_type', JournalEntry::OPENING_BALANCE)->first();

        if ($existing) {
            return redirect()->route('journals.show', $existing);
        }

        return view('opening-balance.create', [
            'accountOptions' => $company->accounts()
                ->where('status', Account::ACTIVE)
                ->orderBy('code')
                ->get()
                ->mapWithKeys(fn (Account $account) => [$account->id => "{$account->code} — {$account->name}"])
                ->all(),
        ]);
    }

    public function store(StoreJournalRequest $request, JournalPostingService $postingService): RedirectResponse
    {
        $company = $request->user()->activeCompany();

        // A company only has one opening position — block a second one
        // instead of silently letting it double-count the starting balance.
        if ($company->journalEntries()->where('journal_type', JournalEntry::OPENING_BALANCE)->exists()) {
            return redirect()->route('opening-balance.show');
        }

        Gate::authorize('journal.post');

        $journal = DB::transaction(function () use ($request, $company) {
            $journal = $company->journalEntries()->create([
                'journal_number' => JournalEntry::nextJournalNumber($company),
                'transaction_date' => $request->validated('transaction_date'),
                'description' => $request->validated('description') ?? 'Opening Balance',
                'status' => JournalEntry::DRAFT,
                'journal_type' => JournalEntry::OPENING_BALANCE,
                'created_by' => $request->user()->id,
            ]);

            $journal->lines()->createMany(
                collect($request->validated('lines'))->map(fn (array $line) => [
                    'account_id' => $line['account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ])->all()
            );

            return $journal;
        });

        try {
            $postingService->post($journal, $request->user());
        } catch (ValidationException $e) {
            // Saved as a draft either way — the user can fix it and post
            // through the ordinary journal show page, same as any journal.
            return redirect()->route('journals.show', $journal)->withErrors($e->errors());
        }

        return redirect()->route('journals.show', $journal)->with('success', "Opening balance {$journal->journal_number} was posted.");
    }
}
