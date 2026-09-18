<?php

namespace App\Services\Cash;

use App\Models\CashTransaction;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Support\Facades\DB;

/**
 * Cash In/Out is a business-convenience layer over the journal engine
 * (biz §17) — it never computes its own balance, and posting is never done
 * here directly. It always generates an ordinary journal and lets
 * JournalPostingService be the only thing that ever posts one (spec §22).
 */
class CashTransactionService
{
    public function __construct(private readonly JournalPostingService $postingService) {}

    /**
     * Cash In: Debit the cash account's linked GL account, Credit the
     * counter account (spec STEP 10).
     */
    public function createCashIn(Company $company, User $user, array $data): CashTransaction
    {
        return $this->create($company, $user, $data, CashTransaction::CASH_IN);
    }

    /**
     * Cash Out: the mirror image — Debit the counter account, Credit the
     * cash account's linked GL account (spec STEP 11).
     */
    public function createCashOut(Company $company, User $user, array $data): CashTransaction
    {
        return $this->create($company, $user, $data, CashTransaction::CASH_OUT);
    }

    private function create(Company $company, User $user, array $data, string $type): CashTransaction
    {
        return DB::transaction(function () use ($company, $user, $data, $type) {
            $cashAccount = $company->cashAccounts()->findOrFail($data['cash_account_id']);
            $description = $data['description'] ?? ($type === CashTransaction::CASH_IN ? 'Cash In' : 'Cash Out');

            $journal = $company->journalEntries()->create([
                'journal_number' => JournalEntry::nextJournalNumber($company),
                'transaction_date' => $data['transaction_date'],
                'description' => $description,
                'status' => JournalEntry::DRAFT,
                'created_by' => $user->id,
            ]);

            $cashLine = ['account_id' => $cashAccount->account_id, 'description' => $data['description'] ?? null];
            $counterLine = ['account_id' => $data['counter_account_id'], 'description' => $data['description'] ?? null];

            if ($type === CashTransaction::CASH_IN) {
                $cashLine += ['debit' => $data['amount'], 'credit' => 0];
                $counterLine += ['debit' => 0, 'credit' => $data['amount']];
            } else {
                $cashLine += ['debit' => 0, 'credit' => $data['amount']];
                $counterLine += ['debit' => $data['amount'], 'credit' => 0];
            }

            $journal->lines()->createMany([$cashLine, $counterLine]);

            return $company->cashTransactions()->create([
                'transaction_number' => CashTransaction::nextTransactionNumber($company),
                'transaction_type' => $type,
                'transaction_date' => $data['transaction_date'],
                'cash_account_id' => $cashAccount->id,
                'counter_account_id' => $data['counter_account_id'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'status' => CashTransaction::DRAFT,
                'journal_entry_id' => $journal->id,
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * Posts the transaction's journal through the one canonical posting
     * service, then — only if that succeeds — marks the transaction itself
     * POSTED. Both updates happen in the same DB transaction: there is no
     * state where the journal is posted but the transaction isn't, or vice
     * versa (spec Invariant 6 / STEP 10 DoD: "posted atomically").
     */
    public function post(CashTransaction $cashTransaction, User $user): CashTransaction
    {
        return DB::transaction(function () use ($cashTransaction, $user) {
            $locked = CashTransaction::whereKey($cashTransaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPosted()) {
                return $locked;
            }

            $this->postingService->post($locked->journalEntry, $user);

            $locked->update([
                'status' => CashTransaction::POSTED,
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    /**
     * A draft cash transaction and its draft journal exist only for each
     * other — deleting one without the other would leave an orphan.
     */
    public function deleteDraft(CashTransaction $cashTransaction): void
    {
        DB::transaction(function () use ($cashTransaction) {
            $journal = $cashTransaction->journalEntry;
            $cashTransaction->delete();
            $journal?->delete();
        });
    }
}
