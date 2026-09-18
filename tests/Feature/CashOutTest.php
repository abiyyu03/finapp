<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashOutTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private CashAccount $mainCash;

    private Account $electricityExpense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'Cash Out Co']);
        $cashGl = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->mainCash = $this->company->cashAccounts()->create(['name' => 'Main Cash', 'account_id' => $cashGl->id, 'status' => CashAccount::ACTIVE]);
        $this->electricityExpense = Account::create(['company_id' => $this->company->id, 'code' => '5100', 'name' => 'Electricity Expense', 'type' => Account::EXPENSE]);
    }

    private function memberWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', $role)->value('id'),
        ]);
        $this->actingAs($user)->get(route('dashboard'));

        return $user;
    }

    public function test_a_cash_out_generates_exactly_one_journal_with_debit_and_credit_reversed_from_cash_in(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $response = $this->post(route('cash-transactions.store-out'), [
            'transaction_date' => '2026-09-01',
            'description' => 'Electricity bill',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->electricityExpense->id,
            'amount' => 500000,
        ]);

        $transaction = CashTransaction::firstOrFail();
        $response->assertRedirect(route('cash-transactions.show', $transaction));

        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(CashTransaction::CASH_OUT, $transaction->transaction_type);

        $journal = JournalEntry::with('lines')->firstOrFail();
        $cashLine = $journal->lines->firstWhere('account_id', $this->mainCash->account_id);
        $expenseLine = $journal->lines->firstWhere('account_id', $this->electricityExpense->id);

        // Mirror of Cash In: counter account is debited, cash account is credited.
        $this->assertSame('500000.00', (string) $expenseLine->debit);
        $this->assertSame('0.00', (string) $expenseLine->credit);
        $this->assertSame('0.00', (string) $cashLine->debit);
        $this->assertSame('500000.00', (string) $cashLine->credit);
    }

    public function test_transaction_and_journal_are_posted_atomically(): void
    {
        $user = $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-out'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->electricityExpense->id,
            'amount' => 500000,
        ]);
        $transaction = CashTransaction::firstOrFail();

        $this->post(route('cash-transactions.post', $transaction))
            ->assertRedirect(route('cash-transactions.show', $transaction));

        $transaction->refresh();
        $this->assertTrue($transaction->isPosted());
        $this->assertTrue($transaction->journalEntry->fresh()->isPosted());
        $this->assertSame($user->id, $transaction->posted_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::POST,
            'resource_type' => JournalEntry::class,
            'resource_id' => $transaction->journal_entry_id,
        ]);
    }

    public function test_no_duplicate_journal_is_created_on_double_post(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-out'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->electricityExpense->id,
            'amount' => 500000,
        ]);
        $transaction = CashTransaction::firstOrFail();

        $this->post(route('cash-transactions.post', $transaction));
        $this->post(route('cash-transactions.post', $transaction));

        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(
            1,
            AuditLog::where('resource_id', $transaction->journal_entry_id)->where('action', AuditLog::POST)->count(),
        );
    }

    public function test_an_inactive_cash_account_cannot_be_used_for_a_new_cash_out(): void
    {
        $this->mainCash->update(['status' => CashAccount::INACTIVE]);
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-out'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->electricityExpense->id,
            'amount' => 500000,
        ])->assertSessionHasErrors('cash_account_id');
    }

    public function test_amount_must_be_greater_than_zero(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-out'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->electricityExpense->id,
            'amount' => -100,
        ])->assertSessionHasErrors('amount');
    }

    public function test_viewer_cannot_create_a_cash_out(): void
    {
        $this->memberWithRole(Role::VIEWER);

        $this->get(route('cash-transactions.create-out'))->assertForbidden();
        $this->post(route('cash-transactions.store-out'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->electricityExpense->id,
            'amount' => 500000,
        ])->assertForbidden();
    }

    public function test_a_draft_cash_out_can_be_deleted_along_with_its_draft_journal(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-out'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->electricityExpense->id,
            'amount' => 500000,
        ]);
        $transaction = CashTransaction::firstOrFail();
        $journalId = $transaction->journal_entry_id;

        $this->delete(route('cash-transactions.destroy', $transaction))
            ->assertRedirect(route('cash-transactions.index'));

        $this->assertModelMissing($transaction);
        $this->assertDatabaseMissing('journal_entries', ['id' => $journalId]);
    }
}
