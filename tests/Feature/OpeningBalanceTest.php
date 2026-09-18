<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Account $cash;

    private Account $equity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'Opening Co']);
        $this->cash = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->equity = Account::create(['company_id' => $this->company->id, 'code' => '3000', 'name' => 'Owner Equity', 'type' => Account::EQUITY]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
        ]);
        $this->actingAs($user)->get(route('dashboard'));

        return $user;
    }

    public function test_a_balanced_opening_balance_is_created_and_posted_immediately(): void
    {
        $this->admin();

        $response = $this->post(route('opening-balance.store'), [
            'transaction_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => 100000000, 'credit' => 0],
                ['account_id' => $this->equity->id, 'debit' => 0, 'credit' => 100000000],
            ],
        ]);

        $journal = JournalEntry::where('journal_type', JournalEntry::OPENING_BALANCE)->firstOrFail();

        $response->assertRedirect(route('journals.show', $journal));
        $this->assertTrue($journal->isPosted());
        $this->assertSame(JournalEntry::OPENING_BALANCE, $journal->journal_type);
        $this->assertSame('100000000.00', $journal->totalDebit());
        $this->assertSame('100000000.00', $journal->totalCredit());
    }

    public function test_an_unbalanced_opening_balance_stays_draft_and_shows_the_error(): void
    {
        $this->admin();

        $this->post(route('opening-balance.store'), [
            'transaction_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => 100000000, 'credit' => 0],
                ['account_id' => $this->equity->id, 'debit' => 0, 'credit' => 90000000],
            ],
        ])->assertSessionHasErrors('journal');

        $journal = JournalEntry::where('journal_type', JournalEntry::OPENING_BALANCE)->firstOrFail();
        $this->assertSame(JournalEntry::DRAFT, $journal->status);

        // It's an ordinary draft now — fixable and postable through the
        // normal journal screens, no special opening-balance retry path.
        $this->get(route('journals.edit', $journal))->assertOk();
    }

    public function test_a_company_can_only_have_one_opening_balance(): void
    {
        $this->admin();

        $this->post(route('opening-balance.store'), [
            'transaction_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->equity->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $this->assertSame(1, JournalEntry::where('journal_type', JournalEntry::OPENING_BALANCE)->count());

        // A second attempt doesn't create a second one — it's redirected
        // back to the existing opening balance instead.
        $this->post(route('opening-balance.store'), [
            'transaction_date' => '2026-02-01',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $this->equity->id, 'debit' => 0, 'credit' => 5000],
            ],
        ])->assertRedirect(route('opening-balance.show'));

        $this->assertSame(1, JournalEntry::where('journal_type', JournalEntry::OPENING_BALANCE)->count());

        $this->get(route('opening-balance.show'))->assertRedirect(
            route('journals.show', JournalEntry::where('journal_type', JournalEntry::OPENING_BALANCE)->firstOrFail())
        );
    }

    public function test_viewer_cannot_set_an_opening_balance(): void
    {
        $viewer = User::factory()->create();
        $viewer->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::VIEWER)->value('id'),
        ]);
        $this->actingAs($viewer)->get(route('dashboard'));

        $this->get(route('opening-balance.show'))->assertForbidden();
        $this->post(route('opening-balance.store'), [
            'transaction_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->equity->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertForbidden();
    }
}
