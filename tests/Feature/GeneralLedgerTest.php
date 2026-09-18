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

class GeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Account $cash;

    private Account $revenue;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'GL Co']);
        $this->cash = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->revenue = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Revenue', 'type' => Account::REVENUE]);

        $this->accountant = User::factory()->create();
        $this->accountant->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::ACCOUNTANT)->value('id'),
        ]);
        $this->actingAs($this->accountant)->get(route('dashboard'));
    }

    private function postJournal(string $date, string $debitAccountId, string $creditAccountId, string $amount): JournalEntry
    {
        $journal = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => $date,
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $journal->lines()->createMany([
            ['account_id' => $debitAccountId, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $amount],
        ]);

        app(JournalPostingService::class)->post($journal, $this->accountant);

        return $journal->fresh();
    }

    public function test_the_ledger_only_shows_posted_journals_for_the_selected_account(): void
    {
        $this->postJournal('2026-09-01', $this->cash->id, $this->revenue->id, '1000000');

        // A draft journal that must NOT appear.
        $draft = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => '2026-09-02',
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $draft->lines()->createMany([
            ['account_id' => $this->cash->id, 'debit' => 999999, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 999999],
        ]);

        $response = $this->get(route('reports.general-ledger', ['account_id' => $this->cash->id]));

        $response->assertOk();
        $response->assertSee('1.000.000');
        $response->assertDontSee('999.999');
    }

    public function test_running_balance_is_correct_for_an_asset_account(): void
    {
        $this->postJournal('2026-09-01', $this->cash->id, $this->revenue->id, '10000000'); // Cash +10,000,000
        $this->postJournal('2026-09-02', $this->cash->id, $this->revenue->id, '5000000');  // Cash +5,000,000

        $response = $this->get(route('reports.general-ledger', ['account_id' => $this->cash->id]));

        $response->assertOk();
        // Running balance after each line: 10,000,000 then 15,000,000.
        $response->assertSeeInOrder(['10.000.000', '15.000.000']);
    }

    public function test_balance_brought_forward_is_correct_when_a_date_from_filter_is_applied(): void
    {
        $this->postJournal('2026-08-01', $this->cash->id, $this->revenue->id, '10000000');
        $this->postJournal('2026-09-05', $this->cash->id, $this->revenue->id, '2000000');

        $response = $this->get(route('reports.general-ledger', [
            'account_id' => $this->cash->id,
            'date_from' => '2026-09-01',
        ]));

        $response->assertOk();
        $response->assertSee('Balance brought forward');
        // Brought forward = the August posting; ending = brought forward + September posting.
        $response->assertSeeInOrder(['10.000.000', '2.000.000', '12.000.000']);
    }

    public function test_a_revenue_accounts_normal_balance_is_credit(): void
    {
        // Revenue increases on the credit side — balance should read as a
        // positive running total driven by credits, not debits.
        $this->postJournal('2026-09-01', $this->cash->id, $this->revenue->id, '3000000');

        $response = $this->get(route('reports.general-ledger', ['account_id' => $this->revenue->id]));

        $response->assertOk();
        $response->assertSee('3.000.000');
    }

    public function test_selecting_no_account_shows_a_prompt_instead_of_a_ledger(): void
    {
        $this->get(route('reports.general-ledger'))
            ->assertOk()
            ->assertSee('Select an account to view its ledger');
    }

    public function test_a_company_cannot_view_another_companys_account_ledger(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $otherAccount = Account::create(['company_id' => $otherCompany->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);

        $response = $this->get(route('reports.general-ledger', ['account_id' => $otherAccount->id]));

        $response->assertOk();
        $response->assertSee('Select an account to view its ledger');
    }

    public function test_viewer_can_view_the_general_ledger(): void
    {
        $viewer = User::factory()->create();
        $viewer->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::VIEWER)->value('id'),
        ]);
        $this->actingAs($viewer)->get(route('dashboard'));

        $this->get(route('reports.general-ledger'))->assertOk();
    }
}
