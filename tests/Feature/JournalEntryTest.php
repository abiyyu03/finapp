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

class JournalEntryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Account $cash;

    private Account $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'Journal Co']);
        $this->cash = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->revenue = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Revenue', 'type' => Account::REVENUE]);
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

    public function test_accountant_can_create_a_draft_journal_with_unbalanced_lines(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $response = $this->post(route('journals.store'), [
            'transaction_date' => '2026-09-01',
            'description' => 'Opening sale',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => '1000000', 'credit' => '0'],
                // Deliberately unbalanced — STEP 5 does not enforce debit = credit.
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '500000'],
            ],
        ]);

        $journal = JournalEntry::first();
        $response->assertRedirect(route('journals.show', $journal));

        $this->assertSame(JournalEntry::DRAFT, $journal->status);
        $this->assertCount(2, $journal->lines);
        $this->assertSame('JRN-000001', $journal->journal_number);
    }

    public function test_a_line_cannot_have_both_debit_and_credit(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('journals.store'), [
            'transaction_date' => '2026-09-01',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => '1000', 'credit' => '1000'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '1000'],
            ],
        ])->assertSessionHasErrors('lines.0.debit');
    }

    public function test_a_line_cannot_have_neither_debit_nor_credit(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('journals.store'), [
            'transaction_date' => '2026-09-01',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => '0', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '1000'],
            ],
        ])->assertSessionHasErrors('lines.0.debit');
    }

    public function test_at_least_two_lines_are_required(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('journals.store'), [
            'transaction_date' => '2026-09-01',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => '1000', 'credit' => '0'],
            ],
        ])->assertSessionHasErrors('lines');
    }

    public function test_a_line_cannot_reference_another_companys_account(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $foreignAccount = Account::create(['company_id' => $otherCompany->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);

        $this->memberWithRole(Role::ACCOUNTANT);

        $this->post(route('journals.store'), [
            'transaction_date' => '2026-09-01',
            'lines' => [
                ['account_id' => $foreignAccount->id, 'debit' => '1000', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '1000'],
            ],
        ])->assertSessionHasErrors('lines.0.account_id');
    }

    public function test_viewer_cannot_create_a_journal(): void
    {
        $this->memberWithRole(Role::VIEWER);

        $this->get(route('journals.create'))->assertForbidden();
        $this->post(route('journals.store'), [
            'transaction_date' => '2026-09-01',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => '1000', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '1000'],
            ],
        ])->assertForbidden();
    }

    public function test_a_draft_journal_can_be_edited_and_deleted(): void
    {
        $user = $this->memberWithRole(Role::ACCOUNTANT);

        $journal = $this->company->journalEntries()->create([
            'journal_number' => 'JRN-000001',
            'transaction_date' => '2026-09-01',
            'status' => JournalEntry::DRAFT,
            'created_by' => $user->id,
        ]);
        $journal->lines()->createMany([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->put(route('journals.update', $journal), [
            'transaction_date' => '2026-09-02',
            'description' => 'Corrected date',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => '2000', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '2000'],
            ],
        ])->assertRedirect(route('journals.show', $journal));

        $journal->refresh();
        $this->assertSame('Corrected date', $journal->description);
        $this->assertSame('2000.00', (string) $journal->totalDebit());

        $this->delete(route('journals.destroy', $journal))->assertRedirect(route('journals.index'));
        $this->assertModelMissing($journal);
    }

    public function test_a_posted_journal_cannot_be_edited_or_deleted(): void
    {
        $user = $this->memberWithRole(Role::ACCOUNTANT);

        $journal = $this->company->journalEntries()->create([
            'journal_number' => 'JRN-000001',
            'transaction_date' => '2026-09-01',
            'status' => JournalEntry::POSTED,
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);
        $journal->lines()->createMany([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->get(route('journals.edit', $journal))->assertForbidden();
        $this->delete(route('journals.destroy', $journal))->assertForbidden();

        $this->assertModelExists($journal);
    }

    public function test_a_company_cannot_view_another_companys_journal(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $otherUser = User::factory()->create();
        $otherUser->memberships()->create([
            'company_id' => $otherCompany->id,
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
        ]);
        $this->actingAs($otherUser)->get(route('dashboard'));
        $otherAccount = Account::create(['company_id' => $otherCompany->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $otherJournal = $otherCompany->journalEntries()->create([
            'journal_number' => 'JRN-000001',
            'transaction_date' => '2026-09-01',
            'status' => JournalEntry::DRAFT,
            'created_by' => $otherUser->id,
        ]);

        $this->memberWithRole(Role::ADMIN);

        $this->get(route('journals.show', $otherJournal))->assertNotFound();
    }
}
