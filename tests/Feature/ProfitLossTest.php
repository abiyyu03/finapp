<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitLossTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Account $cash;

    private Account $revenue;

    private Account $expense;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'PL Co']);
        $this->cash = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->revenue = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Sales Revenue', 'type' => Account::REVENUE]);
        $this->expense = Account::create(['company_id' => $this->company->id, 'code' => '5100', 'name' => 'Electricity Expense', 'type' => Account::EXPENSE]);

        $this->accountant = User::factory()->create();
        $this->accountant->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::ACCOUNTANT)->value('id'),
        ]);
        $this->actingAs($this->accountant)->get(route('dashboard'));
    }

    private function postJournal(string $date, string $debitId, string $creditId, string $amount): JournalEntry
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

        return $journal->fresh();
    }

    public function test_net_profit_equals_revenue_minus_expense(): void
    {
        $this->postJournal('2026-09-01', $this->cash->id, $this->revenue->id, '25000000');
        $this->postJournal('2026-09-05', $this->expense->id, $this->cash->id, '5000000');

        $response = $this->get(route('reports.profit-loss'));

        $response->assertOk();
        $response->assertSee('25.000.000');
        $response->assertSee('5.000.000');
        $response->assertSee('20.000.000'); // Net Profit
        $response->assertSee('Net Profit');
    }

    public function test_draft_journals_do_not_affect_profit_and_loss(): void
    {
        $this->postJournal('2026-09-01', $this->cash->id, $this->revenue->id, '10000000');

        $draft = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => '2026-09-02',
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $draft->lines()->createMany([
            ['account_id' => $this->cash->id, 'debit' => 99999999, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 99999999],
        ]);

        $response = $this->get(route('reports.profit-loss'));

        $response->assertOk();
        $response->assertSee('10.000.000');
        $response->assertDontSee('99.999.999');
    }

    public function test_a_period_filter_excludes_transactions_outside_it(): void
    {
        $this->postJournal('2026-08-01', $this->cash->id, $this->revenue->id, '10000000');
        $this->postJournal('2026-09-01', $this->cash->id, $this->revenue->id, '5000000');

        $response = $this->get(route('reports.profit-loss', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']));

        $response->assertOk();
        $response->assertSee('5.000.000');
        $response->assertDontSee('10.000.000');
    }
}
