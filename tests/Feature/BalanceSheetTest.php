<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\Reporting\BalanceSheetService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Account $cash;

    private Account $equity;

    private Account $revenue;

    private Account $expense;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'BS Co']);
        $this->cash = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->equity = Account::create(['company_id' => $this->company->id, 'code' => '3000', 'name' => 'Owner Equity', 'type' => Account::EQUITY]);
        $this->revenue = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Sales Revenue', 'type' => Account::REVENUE]);
        $this->expense = Account::create(['company_id' => $this->company->id, 'code' => '5100', 'name' => 'Expense', 'type' => Account::EXPENSE]);

        $this->accountant = User::factory()->create();
        $this->accountant->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::ACCOUNTANT)->value('id'),
        ]);
        $this->actingAs($this->accountant)->get(route('dashboard'));
    }

    private function postJournal(string $date, string $debitId, string $creditId, string $amount): void
    {
        $journal = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => $date,
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $journal->lines()->createMany([
            ['account_id' => $debitId, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $creditId, 'debit' => 0, 'credit' => $amount],
        ]);
        app(JournalPostingService::class)->post($journal, $this->accountant);
    }

    public function test_the_dataset_from_spec_18_balances(): void
    {
        // Opening balance
        $this->postJournal('2026-01-01', $this->cash->id, $this->equity->id, '100000000');
        // Revenue
        $this->postJournal('2026-01-05', $this->cash->id, $this->revenue->id, '25000000');
        // Expense
        $this->postJournal('2026-01-06', $this->expense->id, $this->cash->id, '5000000');

        $report = app(BalanceSheetService::class)->asOf($this->company, '2026-01-31');

        $this->assertTrue($report['isBalanced']);
        $this->assertSame('100000000.00', $report['totalEquityAccounts']);
        $this->assertSame('20000000.00', $report['currentYearEarnings']); // 25m revenue - 5m expense
        $this->assertSame('120000000.00', $report['totalEquity']);
        $this->assertSame('120000000.00', $report['totalAssets']); // Cash: 100m + 25m - 5m
        $this->assertSame($report['totalAssets'], $report['totalLiabilitiesAndEquity']);
    }

    public function test_the_balance_sheet_page_shows_no_error_when_the_books_balance(): void
    {
        $this->postJournal('2026-01-01', $this->cash->id, $this->equity->id, '30000000');

        $response = $this->get(route('reports.balance-sheet', ['as_of_date' => '2026-01-31']));

        $response->assertOk();
        $response->assertDontSee('Accounting error');
        $response->assertSee('30.000.000');
    }

    public function test_a_transaction_after_the_as_of_date_is_excluded(): void
    {
        $this->postJournal('2026-01-01', $this->cash->id, $this->equity->id, '10000000');
        $this->postJournal('2026-02-01', $this->cash->id, $this->revenue->id, '99999999');

        $report = app(BalanceSheetService::class)->asOf($this->company, '2026-01-31');

        $this->assertSame('10000000.00', $report['totalAssets']);
        $this->assertTrue($report['isBalanced']);
    }

    public function test_draft_journals_do_not_affect_the_balance_sheet(): void
    {
        $this->postJournal('2026-01-01', $this->cash->id, $this->equity->id, '10000000');

        $draft = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => '2026-01-02',
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $draft->lines()->createMany([
            ['account_id' => $this->cash->id, 'debit' => 50000000, 'credit' => 0],
            ['account_id' => $this->equity->id, 'debit' => 0, 'credit' => 50000000],
        ]);

        $report = app(BalanceSheetService::class)->asOf($this->company, '2026-01-31');

        $this->assertSame('10000000.00', $report['totalAssets']);
        $this->assertTrue($report['isBalanced']);
    }

    public function test_viewer_can_view_the_balance_sheet(): void
    {
        $viewer = User::factory()->create();
        $viewer->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::VIEWER)->value('id'),
        ]);
        $this->actingAs($viewer)->get(route('dashboard'));

        $this->get(route('reports.balance-sheet'))->assertOk();
    }
}
