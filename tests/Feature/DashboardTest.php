<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CashAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'Dash Co']);
        $this->accountant = User::factory()->create();
        $this->accountant->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::ACCOUNTANT)->value('id'),
        ]);
        $this->actingAs($this->accountant)->get(route('dashboard'));
    }

    public function test_the_cash_balance_card_reflects_only_posted_activity_on_linked_cash_accounts(): void
    {
        $cashGl = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $cashAccount = $this->company->cashAccounts()->create(['name' => 'Main Cash', 'account_id' => $cashGl->id, 'status' => CashAccount::ACTIVE]);
        $revenue = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Revenue', 'type' => Account::REVENUE]);

        $journal = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => '2026-09-01',
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $journal->lines()->createMany([
            ['account_id' => $cashGl->id, 'debit' => 7000000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 7000000],
        ]);
        app(JournalPostingService::class)->post($journal, $this->accountant);

        // A draft on the same cash account must NOT move the balance.
        $draft = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => '2026-09-02',
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $draft->lines()->createMany([
            ['account_id' => $cashGl->id, 'debit' => 99999999, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 99999999],
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('7.000.000');
        $response->assertDontSee('99.999.999');
        $this->assertNotNull($cashAccount);
    }

    public function test_recent_journal_entries_and_cash_transactions_appear_on_the_dashboard(): void
    {
        $cashGl = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->company->cashAccounts()->create(['name' => 'Main Cash', 'account_id' => $cashGl->id, 'status' => CashAccount::ACTIVE]);
        $revenue = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Revenue', 'type' => Account::REVENUE]);

        $journal = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => '2026-09-01',
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $journal->lines()->createMany([
            ['account_id' => $cashGl->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $this->company->cashAccounts()->first()->id,
            'counter_account_id' => $revenue->id,
            'amount' => 500000,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($journal->journal_number);
        $response->assertSee('CT-000001');
    }

    public function test_dashboard_shows_zeroes_for_a_brand_new_company_not_invented_numbers(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Rp0');
        $response->assertSee('No journal entries yet.');
        $response->assertSee('No cash transactions yet.');
    }
}
