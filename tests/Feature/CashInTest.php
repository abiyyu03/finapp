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

class CashInTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private CashAccount $mainCash;

    private Account $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'Cash In Co']);
        $cashGl = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->mainCash = $this->company->cashAccounts()->create(['name' => 'Main Cash', 'account_id' => $cashGl->id, 'status' => CashAccount::ACTIVE]);
        $this->revenue = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Sales Revenue', 'type' => Account::REVENUE]);
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

    public function test_a_cash_in_generates_exactly_one_journal(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $response = $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'description' => 'Cash sale',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 2000000,
        ]);

        $transaction = CashTransaction::firstOrFail();
        $response->assertRedirect(route('cash-transactions.show', $transaction));

        $this->assertSame(1, JournalEntry::count());
        $journal = JournalEntry::firstOrFail();
        $this->assertSame($transaction->journal_entry_id, $journal->id);
        $this->assertSame(JournalEntry::DRAFT, $journal->status);
        $this->assertCount(2, $journal->lines);
        $this->assertSame('2000000.00', $journal->totalDebit());
        $this->assertSame('2000000.00', $journal->totalCredit());
    }

    public function test_cash_in_debits_the_cash_accounts_linked_gl_account_and_credits_the_counter_account(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 1000000,
        ]);

        $journal = JournalEntry::with('lines')->firstOrFail();
        $cashLine = $journal->lines->firstWhere('account_id', $this->mainCash->account_id);
        $revenueLine = $journal->lines->firstWhere('account_id', $this->revenue->id);

        $this->assertSame('1000000.00', (string) $cashLine->debit);
        $this->assertSame('0.00', (string) $cashLine->credit);
        $this->assertSame('0.00', (string) $revenueLine->debit);
        $this->assertSame('1000000.00', (string) $revenueLine->credit);
    }

    public function test_transaction_and_journal_are_posted_atomically(): void
    {
        $user = $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 1000000,
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

    public function test_an_inactive_cash_account_cannot_be_used_for_a_new_cash_in(): void
    {
        $this->mainCash->update(['status' => CashAccount::INACTIVE]);
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 1000000,
        ])->assertSessionHasErrors('cash_account_id');

        $this->assertSame(0, CashTransaction::count());
    }

    public function test_amount_must_be_greater_than_zero(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 0,
        ])->assertSessionHasErrors('amount');

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => -500,
        ])->assertSessionHasErrors('amount');
    }

    public function test_a_cash_account_from_another_company_cannot_be_used(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $otherGl = Account::create(['company_id' => $otherCompany->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $foreignCashAccount = $otherCompany->cashAccounts()->create(['name' => 'Their Cash', 'account_id' => $otherGl->id, 'status' => CashAccount::ACTIVE]);

        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $foreignCashAccount->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 1000000,
        ])->assertSessionHasErrors('cash_account_id');
    }

    public function test_viewer_cannot_create_a_cash_in(): void
    {
        $this->memberWithRole(Role::VIEWER);

        $this->get(route('cash-transactions.create-in'))->assertForbidden();
        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 1000000,
        ])->assertForbidden();
    }

    public function test_a_draft_cash_in_can_be_deleted_along_with_its_draft_journal(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 1000000,
        ]);
        $transaction = CashTransaction::firstOrFail();
        $journalId = $transaction->journal_entry_id;

        $this->delete(route('cash-transactions.destroy', $transaction))
            ->assertRedirect(route('cash-transactions.index'));

        $this->assertModelMissing($transaction);
        $this->assertDatabaseMissing('journal_entries', ['id' => $journalId]);
    }

    public function test_a_posted_cash_in_cannot_be_deleted(): void
    {
        $user = $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 1000000,
        ]);
        $transaction = CashTransaction::firstOrFail();
        $this->post(route('cash-transactions.post', $transaction));

        $this->delete(route('cash-transactions.destroy', $transaction))->assertForbidden();
        $this->assertModelExists($transaction->fresh());
    }

    public function test_double_clicking_post_does_not_double_post(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->mainCash->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 1000000,
        ]);
        $transaction = CashTransaction::firstOrFail();

        $this->post(route('cash-transactions.post', $transaction));
        $this->post(route('cash-transactions.post', $transaction));

        $this->assertSame(
            1,
            AuditLog::where('resource_id', $transaction->journal_entry_id)->where('action', AuditLog::POST)->count(),
        );
    }

    public function test_a_company_cannot_view_another_companys_cash_transaction(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $otherUser = User::factory()->create();
        $otherUser->memberships()->create([
            'company_id' => $otherCompany->id,
            'role_id' => Role::where('name', Role::ACCOUNTANT)->value('id'),
        ]);
        $this->actingAs($otherUser)->get(route('dashboard'));
        $otherGl = Account::create(['company_id' => $otherCompany->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $otherCash = $otherCompany->cashAccounts()->create(['name' => 'Their Cash', 'account_id' => $otherGl->id, 'status' => CashAccount::ACTIVE]);
        $otherRevenue = Account::create(['company_id' => $otherCompany->id, 'code' => '4000', 'name' => 'Revenue', 'type' => Account::REVENUE]);
        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $otherCash->id,
            'counter_account_id' => $otherRevenue->id,
            'amount' => 1000000,
        ]);
        $otherTransaction = CashTransaction::firstOrFail();

        $this->memberWithRole(Role::ACCOUNTANT);

        $this->get(route('cash-transactions.show', $otherTransaction))->assertNotFound();
    }
}
