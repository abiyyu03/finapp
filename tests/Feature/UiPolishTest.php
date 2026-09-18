<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class UiPolishTest extends TestCase
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

        $this->company = Company::create(['name' => 'Polish Co']);
        $this->cash = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->revenue = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Revenue', 'type' => Account::REVENUE]);

        $this->accountant = User::factory()->create();
        $this->accountant->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::ACCOUNTANT)->value('id'),
        ]);
        $this->actingAs($this->accountant)->get(route('dashboard'));
    }

    public function test_currency_component_formats_whole_rupiah_with_no_decimals(): void
    {
        $view = $this->blade('<x-currency :amount="1234567" />');
        $view->assertSee('Rp1.234.567', false);
        $view->assertDontSee(',00');

        $plain = $this->blade('<x-currency :amount="1234567" :symbol="false" />');
        $plain->assertSee('1.234.567', false);
        $plain->assertDontSee('Rp1.234.567');
    }

    public function test_journal_index_can_be_filtered_by_status(): void
    {
        $draft = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => '2026-09-01',
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $draft->lines()->createMany([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $response = $this->get(route('journals.index', ['status' => 'POSTED']));

        $response->assertOk();
        $response->assertDontSee($draft->journal_number);
    }

    public function test_cash_transactions_index_can_be_filtered_by_type(): void
    {
        $cashAccount = $this->company->cashAccounts()->create(['name' => 'Main Cash', 'account_id' => $this->cash->id, 'status' => 'ACTIVE']);

        $this->post(route('cash-transactions.store-in'), [
            'transaction_date' => '2026-09-01',
            'cash_account_id' => $cashAccount->id,
            'counter_account_id' => $this->revenue->id,
            'amount' => 1000,
        ]);
        $cashIn = CashTransaction::firstOrFail();

        // Age out the "was saved as a draft" flash from the request above —
        // otherwise its own text (which names the transaction number) would
        // still be on the very next page and give this test a false fail.
        $this->get(route('dashboard'));

        $response = $this->get(route('cash-transactions.index', ['type' => 'CASH_OUT']));

        $response->assertOk();
        $response->assertDontSee($cashIn->transaction_number);
    }

    public function test_a_validation_error_highlights_the_offending_journal_line_row(): void
    {
        $bag = new ViewErrorBag;
        $bag = $bag->put('default', new MessageBag([
            'lines.1.debit' => ['A line cannot have both a debit and a credit amount.'],
        ]));

        $view = $this->blade("@include('journals._form')", [
            'journal' => null,
            'accountOptions' => [],
            'errors' => $bag,
        ]);

        // journalForm's 3rd argument is the list of errored row indexes —
        // row 1 (the second line) must be in it so Alpine can highlight it.
        $view->assertSee('journalForm(', false);
        $view->assertSee('[1]', false);
    }

    public function test_the_post_confirmation_button_is_not_styled_as_a_destructive_action(): void
    {
        $journal = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => '2026-09-01',
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $journal->lines()->createMany([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $response = $this->get(route('journals.show', $journal));

        $response->assertOk();
        // The Post button's own modal form uses the primary (not danger) variant.
        $response->assertSeeInOrder(['post-journal', 'Posting…']);
    }
}
