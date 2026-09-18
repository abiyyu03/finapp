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
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'Audit Co']);
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

    public function test_posting_a_journal_produces_an_audit_record(): void
    {
        $user = $this->memberWithRole(Role::ACCOUNTANT);
        $cash = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $revenue = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Revenue', 'type' => Account::REVENUE]);

        $journal = $this->company->journalEntries()->create([
            'journal_number' => JournalEntry::nextJournalNumber($this->company),
            'transaction_date' => '2026-09-01',
            'status' => JournalEntry::DRAFT,
            'created_by' => $user->id,
        ]);
        $journal->lines()->createMany([
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
        ]);
        app(JournalPostingService::class)->post($journal, $user);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'action' => AuditLog::POST,
            'resource_type' => JournalEntry::class,
            'resource_id' => $journal->id,
        ]);
    }

    public function test_updating_an_account_produces_an_audit_record(): void
    {
        $user = $this->memberWithRole(Role::ADMIN);
        $account = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);

        $this->put(route('accounts.update', $account), [
            'code' => '1000', 'name' => 'Main Cash', 'type' => Account::ASSET, 'status' => Account::ACTIVE,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'action' => AuditLog::UPDATE,
            'resource_type' => Account::class,
            'resource_id' => $account->id,
        ]);

        $log = AuditLog::where('resource_id', $account->id)->where('action', AuditLog::UPDATE)->firstOrFail();
        $this->assertSame('Cash', $log->before_data['name']);
        $this->assertSame('Main Cash', $log->after_data['name']);
    }

    public function test_the_audit_log_screen_lists_records_for_the_active_company_only(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $otherUser = User::factory()->create();
        $otherUser->memberships()->create(['company_id' => $otherCompany->id, 'role_id' => Role::where('name', Role::ADMIN)->value('id')]);
        AuditLog::create([
            'company_id' => $otherCompany->id, 'user_id' => $otherUser->id, 'action' => AuditLog::CREATE,
            'resource_type' => Account::class, 'resource_id' => 999,
        ]);

        $user = $this->memberWithRole(Role::ADMIN);
        $account = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->put(route('accounts.update', $account), [
            'code' => '1000', 'name' => 'Cash Renamed', 'type' => Account::ASSET, 'status' => Account::ACTIVE,
        ]);

        $response = $this->get(route('audit-logs.index'));

        $response->assertOk();
        $response->assertSee('UPDATE');
        $response->assertSee($user->name);
    }

    public function test_only_admin_can_view_audit_logs(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->get(route('audit-logs.index'))->assertForbidden();
    }
}
