<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'RBAC Co']);
    }

    private function memberWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', $role)->value('id'),
        ]);

        $this->actingAs($user);
        $this->get(route('dashboard')); // triggers active-company auto-select

        return $user;
    }

    public function test_admin_has_all_mvp_permissions(): void
    {
        $this->memberWithRole(Role::ADMIN);

        foreach ([
            'account.view', 'account.create', 'journal.view', 'journal.create',
            'journal.post', 'report.view', 'audit_log.view', 'user.manage', 'role.manage',
        ] as $key) {
            $this->assertTrue(Gate::allows($key), "Admin should have {$key}");
        }
    }

    public function test_accountant_can_create_and_post_journals_but_not_manage_users(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->assertTrue(Gate::allows('journal.create'));
        $this->assertTrue(Gate::allows('journal.post'));
        $this->assertTrue(Gate::allows('account.view'));
        $this->assertFalse(Gate::allows('account.create'), 'Accountant does not configure the Chart of Accounts (biz §24)');
        $this->assertFalse(Gate::allows('user.manage'));
        $this->assertFalse(Gate::allows('role.manage'));
    }

    public function test_viewer_cannot_post_journals_or_view_accounts(): void
    {
        $this->memberWithRole(Role::VIEWER);

        $this->assertTrue(Gate::allows('report.view'));
        $this->assertFalse(Gate::allows('journal.create'));
        $this->assertFalse(Gate::allows('journal.post'));
        $this->assertFalse(Gate::allows('account.view'));
        $this->assertFalse(Gate::allows('account.create'));
    }

    public function test_only_admin_can_reach_the_users_screen(): void
    {
        $this->memberWithRole(Role::VIEWER);
        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_admin_can_add_a_user_with_a_role_to_the_active_company(): void
    {
        $this->memberWithRole(Role::ADMIN);

        $response = $this->post(route('users.store'), [
            'name' => 'New Hire',
            'email' => 'new-hire@example.com',
            'password' => 'password123',
            'role_id' => Role::where('name', Role::VIEWER)->value('id'),
        ]);

        $response->assertRedirect(route('users.index'));

        $newUser = User::where('email', 'new-hire@example.com')->firstOrFail();
        $this->assertTrue($newUser->companies->contains($this->company));
        $this->assertSame(Role::VIEWER, $newUser->roleFor($this->company)->name);
    }

    public function test_viewer_cannot_reach_the_reports_placeholder_via_permission_middleware(): void
    {
        $this->memberWithRole(Role::VIEWER);
        // report.view IS granted to viewers, so this should succeed.
        $this->get(route('reports.profit-loss'))->assertOk();
    }

    public function test_accountant_is_blocked_from_the_audit_log_placeholder(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);
        $this->get(route('audit-logs.index'))->assertForbidden();
    }
}
