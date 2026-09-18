<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\Cash\CashTransactionService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExampleTransactionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds one cash account, one bank account, an opening balance, and a
     * few posted cash transactions — so a freshly seeded company shows
     * real numbers on the dashboard and reports instead of all zeros.
     * Everything is posted through JournalPostingService / CashTransactionService,
     * never written directly (spec §22).
     */
    public function run(Company $company, User $user): void
    {
        if ($company->cashTransactions()->exists()) {
            return;
        }

        $kas = Account::where('company_id', $company->id)->where('code', '1100')->firstOrFail();
        $bank = Account::where('company_id', $company->id)->where('code', '1200')->firstOrFail();
        $modal = Account::where('company_id', $company->id)->where('code', '3100')->firstOrFail();
        $penjualan = Account::where('company_id', $company->id)->where('code', '4100')->firstOrFail();
        $gaji = Account::where('company_id', $company->id)->where('code', '5100')->firstOrFail();
        $sewa = Account::where('company_id', $company->id)->where('code', '5200')->firstOrFail();

        $cashAccount = CashAccount::create([
            'company_id' => $company->id,
            'name' => 'Kas Utama',
            'account_id' => $kas->id,
            'status' => CashAccount::ACTIVE,
        ]);

        BankAccount::create([
            'company_id' => $company->id,
            'bank_name' => 'Bank Central Asia',
            'account_name' => $company->name,
            'account_number' => '1234567890',
            'account_id' => $bank->id,
            'status' => BankAccount::ACTIVE,
        ]);

        $postingService = app(JournalPostingService::class);

        $opening = $company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($company),
            'transaction_date' => now()->subDays(30)->toDateString(),
            'description' => 'Opening Balance',
            'status' => JournalEntry::DRAFT,
            'journal_type' => JournalEntry::OPENING_BALANCE,
            'created_by' => $user->id,
        ]);

        $opening->lines()->createMany([
            ['account_id' => $kas->id, 'debit' => 5_000_000, 'credit' => 0],
            ['account_id' => $bank->id, 'debit' => 20_000_000, 'credit' => 0],
            ['account_id' => $modal->id, 'debit' => 0, 'credit' => 25_000_000],
        ]);

        $postingService->post($opening, $user);

        $cashTransactionService = app(CashTransactionService::class);

        $cashIn = $cashTransactionService->createCashIn($company, $user, [
            'transaction_date' => now()->subDays(20)->toDateString(),
            'cash_account_id' => $cashAccount->id,
            'counter_account_id' => $penjualan->id,
            'amount' => 3_500_000,
            'description' => 'Penjualan tunai',
        ]);
        $cashTransactionService->post($cashIn, $user);

        $cashOutGaji = $cashTransactionService->createCashOut($company, $user, [
            'transaction_date' => now()->subDays(10)->toDateString(),
            'cash_account_id' => $cashAccount->id,
            'counter_account_id' => $gaji->id,
            'amount' => 2_000_000,
            'description' => 'Pembayaran gaji',
        ]);
        $cashTransactionService->post($cashOutGaji, $user);

        $cashOutSewa = $cashTransactionService->createCashOut($company, $user, [
            'transaction_date' => now()->subDays(5)->toDateString(),
            'cash_account_id' => $cashAccount->id,
            'counter_account_id' => $sewa->id,
            'amount' => 1_500_000,
            'description' => 'Pembayaran sewa kantor',
        ]);
        $cashTransactionService->post($cashOutSewa, $user);
    }
}
