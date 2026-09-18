<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JournalPostingTest extends TestCase
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

        $this->company = Company::create(['name' => 'Posting Co']);
        $this->cash = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->revenue = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Revenue', 'type' => Account::REVENUE]);

        $this->accountant = User::factory()->create();
        $this->accountant->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::ACCOUNTANT)->value('id'),
        ]);
        $this->actingAs($this->accountant)->get(route('dashboard'));
    }

    private function draftJournal(array $lines): JournalEntry
    {
        $journal = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => '2026-09-01',
            'status' => JournalEntry::DRAFT,
            'created_by' => $this->accountant->id,
        ]);
        $journal->lines()->createMany($lines);

        return $journal;
    }

    public function test_a_balanced_journal_is_posted(): void
    {
        $journal = $this->draftJournal([
            ['account_id' => $this->cash->id, 'debit' => 1000000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000000],
        ]);

        $posted = app(JournalPostingService::class)->post($journal, $this->accountant);

        $this->assertTrue($posted->isPosted());
        $this->assertSame($this->accountant->id, $posted->posted_by);
        $this->assertNotNull($posted->posted_at);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => AuditLog::POST,
            'resource_type' => JournalEntry::class,
            'resource_id' => $journal->id,
        ]);
    }

    public function test_an_unbalanced_journal_is_rejected(): void
    {
        $journal = $this->draftJournal([
            ['account_id' => $this->cash->id, 'debit' => 1000000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 500000],
        ]);

        try {
            app(JournalPostingService::class)->post($journal, $this->accountant);
            $this->fail('Expected a ValidationException for the unbalanced journal.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('journal', $e->errors());
        }

        $this->assertSame(JournalEntry::DRAFT, $journal->fresh()->status);
    }

    public function test_posting_with_an_inactive_account_is_rejected(): void
    {
        $this->cash->update(['status' => Account::INACTIVE]);

        $journal = $this->draftJournal([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->expectException(ValidationException::class);
        app(JournalPostingService::class)->post($journal, $this->accountant);
    }

    public function test_posting_with_a_cross_company_account_is_rejected(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $foreignAccount = Account::create(['company_id' => $otherCompany->id, 'code' => '9999', 'name' => 'Foreign', 'type' => Account::ASSET]);

        $journal = $this->draftJournal([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
        ]);
        // Bypass the app's own guard against cross-company lines to prove
        // the posting service independently catches it too.
        $journal->lines()->create(['account_id' => $foreignAccount->id, 'debit' => 0, 'credit' => 1000]);

        $this->expectException(ValidationException::class);
        app(JournalPostingService::class)->post($journal, $this->accountant);
    }

    public function test_posting_an_already_posted_journal_is_idempotent(): void
    {
        $journal = $this->draftJournal([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $service = app(JournalPostingService::class);
        $service->post($journal, $this->accountant);
        $firstPostedAt = $journal->fresh()->posted_at;

        $service->post($journal->fresh(), $this->accountant);

        $this->assertSame(1, AuditLog::where('resource_id', $journal->id)->count());
        $this->assertEquals($firstPostedAt, $journal->fresh()->posted_at);
    }

    public function test_a_posted_journal_cannot_be_edited_or_deleted_via_http(): void
    {
        $journal = $this->draftJournal([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);
        app(JournalPostingService::class)->post($journal, $this->accountant);

        $this->get(route('journals.edit', $journal))->assertForbidden();
        $this->delete(route('journals.destroy', $journal))->assertForbidden();
    }

    public function test_viewer_cannot_post_a_journal(): void
    {
        $viewer = User::factory()->create();
        $viewer->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', Role::VIEWER)->value('id'),
        ]);

        $journal = $this->draftJournal([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->actingAs($viewer)->get(route('dashboard'));
        $this->post(route('journals.post', $journal))->assertForbidden();

        $this->assertSame(JournalEntry::DRAFT, $journal->fresh()->status);
    }

    public function test_posting_via_http_shows_the_balance_error(): void
    {
        $journal = $this->draftJournal([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 500],
        ]);

        $this->post(route('journals.post', $journal))
            ->assertRedirect(route('journals.show', $journal))
            ->assertSessionHasErrors('journal');

        $this->assertSame(JournalEntry::DRAFT, $journal->fresh()->status);
    }

    public function test_double_clicking_post_via_http_does_not_double_post(): void
    {
        $journal = $this->draftJournal([
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->post(route('journals.post', $journal))->assertRedirect(route('journals.show', $journal));
        $this->post(route('journals.post', $journal))->assertRedirect(route('journals.show', $journal));

        $this->assertSame(1, AuditLog::where('resource_id', $journal->id)->where('action', AuditLog::POST)->count());
    }
}
